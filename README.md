# OpenMage AI Suite

**Bring AI to the stores that never left Magento 1.**

Roughly 150,000 storefronts still run Magento 1 or its maintained fork, OpenMage. They kept a platform that works and skipped a costly replatform. What they lost is access to everything the commerce world has built since: conversational merchandising, AI shopping agents, and admin copilots.

This suite closes that gap. It adds four capabilities to an OpenMage store, all running on your own infrastructure with your own API key.

| | |
|---|---|
| **Talk to your store** | Connect Claude, ChatGPT, Claude Code or any MCP client to your catalog, orders, customers and reports. 22 tools, scoped to an admin role. |
| **Write with your store's context** | Generate product and category copy, and customer service replies, from real product data rather than a blank prompt. |
| **Sell through conversation** | A storefront assistant that searches the live catalog, quotes real prices, tracks orders and adds to cart. |
| **Be found by AI agents** | A product feed, `llms.txt` and a catalog API so AI shopping surfaces can read your inventory. |

Requires OpenMage LTS 20.x or 21.x on PHP 8.1 or newer. MIT licensed.

---

## Why this matters

AI referral traffic to retail sites grew several hundred percent year over year, and shopping agents now transact on behalf of customers. Adobe Commerce ships semantic search, catalog agents and agentic checkout. Magento 2 has commercial MCP servers. Magento 1 merchants have had nothing.

The cost of staying invisible is not theoretical. A store whose catalog no agent can read does not appear in agent-mediated purchases, and a support team without drafting assistance answers the same questions by hand.

This suite is the missing layer, and it installs in an afternoon.

---

## What you get

### Talk to your store from any AI client

An endpoint implementing the [Model Context Protocol](https://modelcontextprotocol.io) over Streamable HTTP. Connect Claude Desktop, Claude Code, ChatGPT or a custom agent and ask questions in plain language.

> *"Which products are low on stock, and how many of each did we sell in the last 90 days?"*

![Ask Your Store](docs/images/ask-your-store.jpg)

The same tools power an **Ask Your Store** panel inside the admin, so staff without an AI subscription get the same capability. Every answer shows which tools produced it.

**Available tools**

| Area | Tools |
|---|---|
| Catalog | search_products, get_product, update_product, list_categories, list_attributes |
| Inventory | get_stock, update_stock |
| Sales | list_orders, get_order, add_order_comment |
| Customers | search_customers, get_customer |
| Reporting | sales_summary, bestsellers, low_stock, abandoned_carts |
| Content | get_cms_page, update_cms_page, list_cart_price_rules |
| Platform | describe_store, get_config, sql_readonly |

### Changes require a human

The assistant proposes; a person approves. Write operations are held and shown with their exact arguments before anything is executed.

![Write confirmation](docs/images/write-confirmation.jpg)

Three independent controls gate every write: a store-wide switch, per-token scope, and this confirmation step.

### Generate content grounded in your catalog

A generate action on product and category pages produces descriptions and metadata from the product's real attributes. Review each field side by side with the current value and apply only what you want.

![Content generation](docs/images/content-generation.jpg)

Bulk generation runs from the product grid. Results are queued as drafts for review, never written directly, so a run across a thousand products stays reversible. Order pages gain a reply drafter for customer service.

### A storefront assistant that knows your inventory

A chat widget that searches the live catalog, quotes the shopper's real prices including their customer group, surfaces promotions, tracks orders and offers add-to-cart.

![Storefront assistant](docs/images/storefront-assistant.jpg)

It works with any theme. No template edits, no jQuery or Prototype conflicts. Transcripts and the most asked questions appear in the admin, which turns customer language into merchandising insight.

### Discoverable by AI shopping agents

- A product feed in JSON Lines and CSV using the Google Merchant and Agentic Commerce Protocol vocabulary, including variants, GTIN, availability and sale pricing
- `llms.txt` describing the store and pointing agents at machine-readable data
- A paginated catalog API so agents read structured data instead of scraping HTML

---

## Built for merchants who need control

**Your keys, your server.** Anthropic, OpenAI or any OpenAI-compatible endpoint including self-hosted models, and Google Gemini. Keys are encrypted at rest. No traffic passes through a third-party service.

**Permissions you already defined.** Each MCP token runs as a specific admin user. A user who cannot see orders cannot let an AI see orders. Role checks are enforced on every call.

**A complete record.** Every tool call and model request is logged with actor, arguments, result, token count and duration.

![Audit log](docs/images/audit-log.jpg)

**Access you can revoke.** Tokens carry a scope, an optional tool allow-list and an expiry, and can be revoked instantly.

![MCP tokens](docs/images/mcp-tokens.jpg)

**Spending limits.** A monthly token budget and per-actor rate limits, with usage broken down by provider, model and feature.

**Nothing on by default.** Every capability ships disabled. Turn on one feature, or turn the whole suite off with a single switch.

**Privacy controls.** Optional masking of customer email addresses and phone numbers before any text reaches a model provider. Transcript retention is configurable and enforced nightly.

---

## Installation

Requires OpenMage LTS 20.x or 21.x on PHP 8.1+. Installation touches only `app/` and `skin/`; no core files are modified.

### Composer

```bash
composer require magento-hackathon/magento-composer-installer jestr-ai/openmage-ai-suite
```

If the package is not yet on Packagist, point Composer at the repository first:

```bash
composer config repositories.ainative vcs https://github.com/jestr-ai/openmage-ai-suite
composer require jestr-ai/openmage-ai-suite:dev-main
```

### modman

```bash
modman init            # skip if already initialised
modman clone https://github.com/jestr-ai/openmage-ai-suite
```

### Manual

Download the latest release and copy the `app/` and `skin/` directories into your OpenMage root.

### After installing

1. Flush the cache: **System > Cache Management > Flush Magento Cache**
2. Log out and back in so the new permissions apply
3. Open **System > Configuration > AI Suite**

---

## Configuration

**Step 1. Add a provider.** Under *AI Suite > Core & Providers*, enable the suite, choose a provider, paste the API key and set a model. Write a short *Store Context* describing what you sell and in what tone; it is prepended to every prompt and materially improves output.

**Step 2. Turn on what you want.** Each capability has its own section: MCP Server, Admin Copilot, Storefront Assistant, LLM Discovery. Leave *Allow Write Tools* off until you have used the read-only tools for a while.

**Step 3. Connect a client.** Create a token under *AI Suite > MCP Access Tokens*. Choose the admin user it runs as, pick read or read and write, and optionally restrict it to specific tools and an expiry date. The token is shown once.

| Client | Connection |
|---|---|
| Claude Code | `claude mcp add --transport http openmage https://your-store.example/ainative-mcp --header "Authorization: Bearer TOKEN"` |
| Claude Desktop, claude.ai | Add a custom connector pointing at `https://your-store.example/ainative-mcp/index/index/t/TOKEN` |
| MCP Inspector | `npx @modelcontextprotocol/inspector`, Streamable HTTP, with the Authorization header |
| Other MCP clients | Streamable HTTP with a Bearer token |

For browser-based clients, add their origin under *Allowed Origins*. Command-line clients send no origin header and are permitted.

Ask something real to confirm it works, for example: *"What were sales yesterday compared with the same day last week, and which products are low on stock?"*

---

## Security

| Control | Behaviour |
|---|---|
| Credentials | Encrypted with the store's encryption key, never written to logs |
| Permissions | Enforced per call against the admin role of the token's owner |
| Write operations | Disabled by default; require a write-scoped token and explicit confirmation |
| SQL access | Disabled by default. When enabled: single SELECT only, blocked table prefixes covering admin, OAuth, session, configuration, customer and payment tables, an enforced row limit, a statement timeout and a read connection |
| Configuration reads | Restricted to non-sensitive sections; anything resembling a credential is withheld |
| Endpoint hardening | Origin validation, protocol version checks and per-token rate limiting |
| Prompt injection | Tool results are passed as data and system prompts instruct the model to ignore instructions embedded in product text, reviews or comments |

Storefront visitors reach a separate, read-only tool set. Guests must supply both an order number and the matching email address to see order details.

---

## Extending

Tools are registered through configuration, so a third-party module can add its own:

```php
class Vendor_Module_Model_Tool_Example extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string { return 'my_tool'; }
    public function getDescription(): string { return 'What this returns and when to use it.'; }
    public function getInputSchema(): array { return $this->schema(['id' => ['type' => 'integer']], ['id']); }
    public function getAclResource(): ?string { return 'admin/catalog/products'; }
    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array { return ['ok' => true]; }
}
```

```xml
<global>
    <ainative_tools>
        <admin>
            <my_tool><class>vendor_module/tool_example</class></my_tool>
        </admin>
    </ainative_tools>
</global>
```

It immediately becomes available to the MCP server and the admin assistant, with schema validation, permission checks, write gating, rate limiting and audit logging applied automatically.

---

## Development

```bash
dev/setup-openmage.sh
```

This provisions a complete OpenMage 20.18 store with sample data in ddev, links this repository with modman and enables an offline mock provider so every flow can be exercised without API spend.

```bash
ddev exec 'cd /var/www/suite && OPENMAGE_ROOT=/var/www/html php /var/www/html/vendor/bin/phpunit -c .phpunit.dist.xml'
```

See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for architecture notes and platform specifics.

---

## Roadmap

| Version | Scope |
|---|---|
| 1.1 | OAuth 2.1 for MCP, streaming responses, semantic search with embeddings, standalone widget embed for non-Magento sites |
| 2.0 | Agentic Commerce Protocol checkout, Unified Commerce Protocol support |

---

## Licence

MIT. See [LICENSE](LICENSE). Not affiliated with or endorsed by Adobe. Magento is a trademark of Adobe Inc.
