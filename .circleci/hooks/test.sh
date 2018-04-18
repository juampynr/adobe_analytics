#!/bin/bash -ex

export SIMPLETEST_BASE_URL="http://localhost"
export SIMPLETEST_DB="sqlite://localhost//tmp/drupal.sqlite"
export BROWSERTEST_OUTPUT_DIRECTORY="/var/www/html/sites/simpletest"

if [ ! -f dependencies_updated ]
then
  ./update-dependencies.sh $1
fi

robo override:phpunit-config $1

mkdir -p modules/adobe_analytics/build/logs
chown www-data:www-data modules/adobe_analytics/build/logs
timeout 60m sudo -E -u www-data vendor/bin/phpunit --verbose --debug -c core --group adobe_analytics --coverage-xml artifacts/coverage-xml --coverage-clover modules/adobe_analytics/build/logs/clover.xml || true
tar czf artifacts/coverage.tar.gz -C artifacts coverage-xml
cd modules/adobe_analytics
../../vendor/bin/php-coveralls -v
