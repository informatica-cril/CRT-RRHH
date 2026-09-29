// Desplegament únic de CRT RRHH (web + API + panell) a https://crtrrhh.crtbcn.cat
//
// Tot és una sola aplicació Laravel: la web Vue ja va compilada a public/app i el panell
// Inertia a public/build (tots dos versionats), de manera que el servidor NO necessita Node.
//
// Requereix a Jenkins:
//   - Plugin "SSH Agent"
//   - Plugin "GitHub" (per al trigger githubPush())
//   - Una credencial "SSH Username with private key" amb l'ID de SSH_CRED_ID
pipeline {
    agent any
    options {
        timestamps()
        disableConcurrentBuilds()
    }
    environment {
        SSH_CRED_ID   = 'crt-ssh-deploy'
        SSH_HOST      = 'CAMBIAR_HOST_O_IP'
        SSH_USER      = 'administrador'
        REMOTE_PATH   = '/www/wwwroot/crtrrhh.crtbcn.cat'
        DEPLOY_BRANCH = 'main'
        APP_URL       = 'https://crtrrhh.crtbcn.cat'
    }
    triggers {
        githubPush()
    }
    stages {
        stage('Deploy') {
            steps {
                script {
                    def remoteScript = '''
set -euo pipefail
cd "__REMOTE_PATH__"

# -- Punt de rollback: commit actual + còpia de la BD abans de migrar --
PREV=$(git rev-parse HEAD)
echo "$PREV" > storage/.last_deploy_commit
echo "Commit previ (rollback de codi): $PREV"

DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
mkdir -p storage/backups
BK="storage/backups/pre-deploy-$(date +%F-%H%M%S).sql.gz"
mysqldump --single-transaction --quick -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BK"
echo "Còpia de la BD: $BK ($(du -h "$BK" | cut -f1))"
ls -1t storage/backups/*.sql.gz | tail -n +11 | xargs -r rm -f

# -- Avorta si hi ha canvis locals sense commit: s'han de resoldre a mà --
if [ -n "$(git status --porcelain)" ]; then
  echo "El servidor té canvis locals sense commit. S'avorta per no perdre'ls."
  git status --porcelain
  exit 1
fi

# -- Desplegament --
git pull origin "__DEPLOY_BRANCH__"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# -- Health-check: API i web --
sleep 2
API=$(curl -s -o /dev/null -w '%{http_code}' "__APP_URL__/api/v1/health" || echo 000)
WEB=$(curl -s -o /dev/null -w '%{http_code}' "__APP_URL__/app/" || echo 000)
if [ "$API" != "200" ] || [ "$WEB" != "200" ]; then
  echo "Health-check ha fallat (API $API, web $WEB). Commit previ: $PREV . Còpia BD: $BK"
  echo "Rollback: git reset --hard $PREV && php artisan migrate:rollback --force && restaurar $BK si cal."
  exit 1
fi
echo "Desplegat i verificat (API 200, web 200). Còpia BD: $BK . Commit previ: $PREV"
'''.replace('__REMOTE_PATH__', env.REMOTE_PATH)
   .replace('__DEPLOY_BRANCH__', env.DEPLOY_BRANCH)
   .replace('__APP_URL__', env.APP_URL)

                    sshagent(credentials: [env.SSH_CRED_ID]) {
                        writeFile file: 'remote_deploy.sh', text: remoteScript
                        sh 'ssh -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" bash -s < remote_deploy.sh'
                    }
                }
            }
        }
    }
    post {
        success { echo '✅ Deploy CRT RRHH OK' }
        failure { echo '❌ Deploy CRT RRHH ha fallat — revisa la consola abans de reintentar.' }
        always  { sh 'rm -f remote_deploy.sh' }
    }
}
