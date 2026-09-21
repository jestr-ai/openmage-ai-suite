#!/usr/bin/env bash
# Stand up a local OpenMage 20.x (ddev) with sample data and this suite linked via modman.
# Usage: dev/setup-openmage.sh [openmage-tag]      → http://ainative-openmage.ddev.site  (admin: admin / see output)
set -euo pipefail
TAG="${1:-v20.18.0}"
HERE="$(cd "$(dirname "$0")" && pwd)"
TARGET="$HERE/openmage"
ADMIN_PW="${ADMIN_PW:-AiSuite-Dev-2026!}"

if [ ! -d "$TARGET/.git" ]; then
  git clone --depth 1 --branch "$TAG" https://github.com/OpenMage/magento-lts.git "$TARGET"
fi
cd "$TARGET"
if [ ! -f .ddev/config.yaml ]; then
  ddev config --project-type=magento --project-name=ainative-openmage --php-version=8.3 --database=mariadb:10.11 --docroot=. \
    --web-environment-add="MAGE_IS_DEVELOPER_MODE=1" --disable-settings-management
fi
mkdir -p .ddev/web-build
cat > .ddev/docker-compose.suite.yaml <<'YAML'
services:
  web:
    volumes:
      - "../../..:/var/www/suite:cached"
YAML
cat > .ddev/web-build/Dockerfile.modman <<'DOCKER'
RUN curl -sSL https://raw.githubusercontent.com/colinmollenhour/modman/master/modman -o /usr/local/bin/modman && chmod +x /usr/local/bin/modman
DOCKER
ddev start -y
ddev composer install --no-interaction --prefer-dist
ddev openmage-install -q || true
ddev exec "cd /var/www/html && (test -d .modman || modman init) && (modman link /var/www/suite || modman repair)"
ddev exec "php -r 'require \"app/Mage.php\"; Mage::app(\"admin\"); Mage::app()->getCacheInstance()->flush(); Mage::getConfig()->reinit(); Mage_Core_Model_Resource_Setup::applyAllUpdates(); \$u=Mage::getModel(\"admin/user\")->loadByUsername(\"admin\"); if(\$u->getId()){\$u->setPassword(\"$ADMIN_PW\")->save();} Mage::getConfig()->saveConfig(\"admin/security/use_form_key\",0); Mage::getConfig()->saveConfig(\"ainative/general/enabled\",1); Mage::getConfig()->saveConfig(\"ainative/general/provider\",\"mock\"); Mage::getConfig()->saveConfig(\"ainative_mcp/general/enabled\",1); Mage::getConfig()->saveConfig(\"ainative_assistant/general/enabled\",1); Mage::getConfig()->saveConfig(\"ainative_discovery/general/enabled\",1); Mage::app()->getCacheInstance()->flush();'"
echo
echo "DONE  store: http://ainative-openmage.ddev.site   admin: http://ainative-openmage.ddev.site/admin  (admin / $ADMIN_PW)"
echo "      provider is 'mock' (offline). Set a real provider + key under System > Configuration > AI Suite."
echo "      MCP token:  ddev exec 'php /var/www/suite/dev/scripts/mcp-token.php read 0'"
echo "      tests:      ddev exec 'cd /var/www/suite && OPENMAGE_ROOT=/var/www/html php /var/www/html/vendor/bin/phpunit -c .phpunit.dist.xml'"
