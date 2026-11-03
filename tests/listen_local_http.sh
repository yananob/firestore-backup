#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_myapps-common/test/export_secrets.sh ${SECRETS[*]}

# Launch function
export FUNCTION_TARGET=main_http
GCP_PROJECT_ID=nobu5-393106 APP_ENV=local php8.4 -S localhost:${PORT:-8080} vendor/bin/router.php


source ./_myapps-common/test/unset_secrets.sh ${SECRETS[*]}
