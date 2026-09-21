# Development notes

## Local environment

`dev/setup-openmage.sh` clones OpenMage v20.18.0 into `dev/openmage` (git-ignored), starts ddev (`ainative-openmage`, http://ainative-openmage.ddev.site), installs sample data, mounts this repository at `/var/www/suite` in the web container and links it with modman. It sets the admin password to `AiSuite-Dev-2026!` (override with `ADMIN_PW=…`), enables all modules and selects the **mock** provider.

Helpers in `dev/scripts/`:

| script | purpose |
|---|---|
| `mcp-token.php read\|write 0\|1` | enable the modules and print a fresh MCP token (arg 2 = allow write tools) |
| `admin-check.sh` | log into the admin with curl, load every AI Suite page, run the Ask / Copilot / job flows |
| `smoke-core.php`, `smoke-copilot.php` | in-process smoke tests of Core and Copilot |

Tests: `ddev exec 'cd /var/www/suite && OPENMAGE_ROOT=/var/www/html php /var/www/html/vendor/bin/phpunit -c .phpunit.dist.xml'`.

The mock provider (`provider = mock`, only listed in developer mode) is deterministic and offline: `[[call:tool_name {"json":"args"}]]` in a user message makes it call that tool and then summarise the result; JSON generation returns `MOCK <field>` values.

After changing any `config.xml`, flush the cache from CLI. After adding design/skin directories, run `modman repair` in the container: modman skips mappings whose source did not exist at link time.

## OpenMage 20.x pitfalls we hit

- Frontend API endpoints receive a `PHPSESSID` cookie from `Mage_Log`'s visitor observer even with `FLAG_NO_START_SESSION`. Register `AiNative_Core_Model_Visitor_Noop::install()` in `preDispatch` and list the route under `global/ignoredModules/entities`.
- Saving a product loaded in the frontend area fails in `_collectSaveData` (original data is null). MCP runs every tool under `core/app_emulation` (store 0, area adminhtml); write tools also call `setOrigData()` defensively.
- Admin controllers must declare `protected function _isAllowed(): bool`.
- Custom routers must be registered under `<global><events><controller_front_init_routers>`; `<frontend><events>` fires too late.
- Product collections: use `addPriceData($groupId, $websiteId)` (alias `price_index`); `addMinimalPrice()` no longer exists. Wrap ORDER BY expressions in `Zend_Db_Expr`.
- Read the store timezone from `general/locale/timezone` for the target store; the locale singleton reflects the request store.
- Setup scripts run only after the config cache is flushed and re-initialised.

## Adding a tool

Implement `AiNative_Core_Model_Tool_Interface` (extend `AiNative_Core_Model_Tool_Abstract` for helpers), register it in `config.xml` under `global/ainative_tools/admin` or `global/ainative_tools/storefront`. The executor applies schema validation and coercion, the write gate, the admin ACL (`getAclResource()`), rate limiting, PII redaction (storefront) and audit logging. Descriptions are read by the model: state what the tool returns and when to use it.
