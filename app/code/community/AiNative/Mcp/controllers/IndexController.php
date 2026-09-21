<?php

/**
 * MCP Streamable HTTP endpoint: POST /ainative-mcp
 * - JSON-RPC request  → application/json response
 * - notification      → 202
 * - GET               → 405 (no server-initiated stream in v1)
 * @license MIT
 */
class AiNative_Mcp_IndexController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        // No session cookies / SID rewriting on an API endpoint.
        $this->setFlag('', self::FLAG_NO_START_SESSION, true);
        Mage::app()->setUseSessionInUrl(false);
        AiNative_Core_Model_Visitor_Noop::install();
        return parent::preDispatch();
    }

    public function indexAction(): void
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        $response->setHeader('Cache-Control', 'no-store', true);
        $response->setHeader('X-Content-Type-Options', 'nosniff', true);

        $helper = Mage::helper('ainative_mcp');
        if (!$helper->isEnabled()) {
            $this->fail(404, 'MCP endpoint is disabled.');
            return;
        }

        // Origin validation (DNS rebinding protection)
        $origin = trim((string) $request->getHeader('Origin'));
        if ($origin !== '' && !in_array(rtrim($origin, '/'), $helper->getAllowedOrigins(), true)) {
            $this->fail(403, 'Origin not allowed.');
            return;
        }

        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            $response->setHttpResponseCode(204);
            $this->cors($origin);
            return;
        }
        if ($method === 'GET') {
            $response->setHttpResponseCode(405);
            $response->setHeader('Allow', 'POST, OPTIONS', true);
            $this->cors($origin);
            $this->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'This server does not offer a server-initiated SSE stream. Use POST.']]);
            return;
        }
        if ($method === 'DELETE') {
            $response->setHttpResponseCode(405); // stateless: nothing to terminate
            return;
        }
        if ($method !== 'POST') {
            $this->fail(405, 'Method not allowed.');
            return;
        }

        $version = trim((string) $request->getHeader('MCP-Protocol-Version'));
        if ($version !== '' && !in_array($version, AiNative_Mcp_Model_Server::SUPPORTED_VERSIONS, true)) {
            $this->fail(400, 'Unsupported MCP-Protocol-Version: ' . $version);
            return;
        }

        // Authentication
        $token = $this->resolveToken();
        if ($token === null) {
            $response->setHeader('WWW-Authenticate', 'Bearer realm="openmage-mcp"', true);
            $this->fail(401, 'Missing or invalid access token.');
            return;
        }
        $user = $token->getAdminUser();
        if (!$user || !(int) $user->getIsActive()) {
            $this->fail(403, 'Token owner is not an active admin user.');
            return;
        }
        $token->touch((string) $request->getClientIp());
        $context = AiNative_Core_Model_Tool_Context::forAdmin('mcp', $user, $token->getScopes())
            ->withMeta('token_id', (int) $token->getId())
            ->withMeta('token_name', $token->getName());

        // Body
        $raw = (string) $request->getRawBody();
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->cors($origin);
            $response->setHttpResponseCode(400);
            $this->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => AiNative_Mcp_Model_Server::ERR_PARSE, 'message' => 'Parse error']]);
            return;
        }

        try {
            Mage::getSingleton('ainative_core/rateLimit')->hit('mcp:' . $token->getId(), max(60, Mage::helper('ainative_core')->getRateLimitPerMinute() * 4));
        } catch (AiNative_Core_Exception $e) {
            $this->fail(429, $e->getMessage());
            return;
        }

        $server = Mage::getModel('ainative_mcp/server', ['context' => $context, 'allowed_tools' => $token->getAllowedTools()]);
        $isBatch = array_is_list($decoded) && $decoded !== [];
        $messages = $isBatch ? $decoded : [$decoded];
        $responses = [];
        // Tools act as an admin: run them in the admin area (no flat-catalog resources, admin locale/design).
        $emulation = Mage::getSingleton('core/app_emulation');
        $initialEnv = $emulation->startEnvironmentEmulation(Mage_Core_Model_App::ADMIN_STORE_ID, Mage_Core_Model_App_Area::AREA_ADMINHTML);
        try {
            foreach ($messages as $msg) {
                $r = $server->handle(is_array($msg) ? $msg : []);
                if ($r !== null) {
                    $responses[] = $r;
                }
            }
        } finally {
            $emulation->stopEnvironmentEmulation($initialEnv);
        }
        $this->cors($origin);
        if ($responses === []) {
            $response->setHttpResponseCode(202);
            return;
        }
        $this->json($isBatch ? $responses : $responses[0]);
    }

    private function resolveToken(): ?AiNative_Mcp_Model_Token
    {
        $request = $this->getRequest();
        $secret = '';
        $auth = (string) ($request->getHeader('Authorization') ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
            $secret = $m[1];
        } elseif (Mage::helper('ainative_mcp')->isUrlTokenAllowed()) {
            $secret = (string) $request->getParam('t', '');
        }
        if ($secret === '') {
            Mage::helper('ainative_core')->debug('MCP auth: no token in request', ['auth_header' => $auth !== '' ? 'present' : 'absent', 'url_token_allowed' => Mage::helper('ainative_mcp')->isUrlTokenAllowed()]);
            return null;
        }
        $token = Mage::getModel('ainative_mcp/token')->loadBySecret($secret);
        if (!$token->isUsable()) {
            Mage::helper('ainative_core')->debug('MCP auth: token rejected', ['prefix' => substr($secret, 0, 12), 'found' => (bool) $token->getId()]);
        }
        return $token->isUsable() ? $token : null;
    }

    private function cors(string $origin): void
    {
        if ($origin !== '') {
            $this->getResponse()
                ->setHeader('Access-Control-Allow-Origin', $origin, true)
                ->setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS', true)
                ->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Session-Id', true)
                ->setHeader('Access-Control-Expose-Headers', 'MCP-Protocol-Version', true)
                ->setHeader('Vary', 'Origin', true);
        }
    }

    private function json(array $payload): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setBody(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}');
    }

    private function fail(int $status, string $message): void
    {
        $this->getResponse()->setHttpResponseCode($status);
        $this->json(['error' => $message]);
    }
}
