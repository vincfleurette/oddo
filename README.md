# Oddo Slim API

API REST moderne construite avec Slim PHP pour l'intégration avec les services Oddo.

## 🚀 Fonctionnalités

-   **Authentification JWT** sécurisée avec API Oddo
-   **Cache intelligent** (file/Redis/database)
-   **Architecture propre** avec services et DTOs
-   **Mode développement** avec données mock
-   **Support Docker** pour le déploiement
-   **Gestion d'erreurs** centralisée

## 📋 Prérequis

-   PHP 8.1+
-   Composer
-   Extension PHP: `mbstring`, `json`, `curl`
-   (Optionnel) Redis pour le cache
-   (Optionnel) MySQL/PostgreSQL pour le stockage

## 🛠️ Installation

### 1. Cloner le projet

```bash
git clone <repository-url>
cd oddoslim-api
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration

```bash
# Copier le template de configuration
cp .env.example .env

# Éditer le fichier .env avec vos paramètres
nano .env
```

### 4. Configuration minimale requise

```bash
# URL de l'API Oddo (fournie par Oddo)
ODDO_BASE_URI=https://your-actual-oddo-api.com/

# Clé secrète JWT (générer une clé aléatoire)
JWT_SECRET=$(openssl rand -base64 32)
```

### 5. Créer le dossier de stockage

```bash
mkdir -p storage
chmod 755 storage
```

## 🐳 Déploiement Docker

### Développement

```bash
docker-compose up -d
```

### Production

```bash
# Construire l'image
docker build -f php/Dockerfile -t oddoslim-api .

# Déployer
docker run -d \
  -p 8000:80 \
  -v $(pwd)/.env:/var/www/html/.env:ro \
  -v $(pwd)/storage:/var/www/html/storage \
  oddoslim-api
```

## 📖 API Endpoints

### Authentification

```http
POST /login
Content-Type: application/json

{
  "user": "your_username",
  "pass": "your_password"
}
```

### Comptes (nécessite JWT)

```http
GET /accounts
Authorization: Bearer <jwt_token>
```

### Cache (nécessite JWT)

```http
GET /cache/info
DELETE /cache
POST /cache/refresh
Authorization: Bearer <jwt_token>
```

### Santé

```http
GET /status
```

## 🧪 Mode Développement

Pour tester sans API Oddo réelle :

1. Garder l'URL d'exemple dans `.env` :

    ```bash
    ODDO_BASE_URI=https://your-oddo-api-url.com/api/
    ```

2. Utiliser les identifiants de test :
    ```json
    {
        "user": "demo",
        "pass": "demo"
    }
    ```

## ⚙️ Configuration Avancée

### Cache Redis

```bash
STORAGE_DRIVER=redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_password
```

### Base de données

```bash
STORAGE_DRIVER=database
DATABASE_DSN=mysql:host=localhost;dbname=oddo
DATABASE_USERNAME=user
DATABASE_PASSWORD=password
```

## 🔧 Scripts de Développement

```bash
# Tests
composer test

# Analyse statique
composer analyse

# Vérification du style de code
composer cs-check

# Correction automatique du style
composer cs-fix
```

## 📁 Structure du Projet

```
├── public/           # Point d'entrée web
│   └── index.php     # Bootstrap de l'application
├── src/
│   ├── Config/       # Configuration
│   │   └── AppConfig.php
│   ├── DTO/          # Objets de transfert de données
│   │   ├── AccountDTO.php
│   │   ├── AccountWithPositionsDTO.php
│   │   └── PositionDTO.php
│   ├── External/     # Clients API externes
│   │   ├── OddoApiClient.php
│   │   └── OddoApiClientInterface.php
│   ├── Middleware/   # Middlewares HTTP
│   │   ├── ErrorHandlerMiddleware.php
│   │   └── JwtMiddleware.php
│   ├── Routes/       # Définition des routes
│   │   ├── AccountRoutes.php
│   │   ├── AuthRoutes.php
│   │   ├── CacheRoutes.php
│   │   └── HealthRoutes.php
│   ├── Services/     # Logique métier
│   │   ├── AuthService.php
│   │   ├── CacheService.php
│   │   ├── OddoApiService.php
│   │   └── PortfolioService.php
│   └── Storage/      # Gestion du stockage
│       ├── Drivers/
│       │   ├── DatabaseStorageDriver.php
│       │   ├── FileStorageDriver.php
│       │   └── RedisStorageDriver.php
│       ├── StorageDriverInterface.php
│       └── StorageManager.php
├── storage/          # Données en cache
├── docker-compose.yml
├── .env.example      # Template de configuration
└── composer.json
```

## 🔒 Sécurité

### Variables d'environnement

-   ✅ Fichier `.env` exclu de Git
-   ✅ JWT secret de minimum 32 caractères
-   ✅ Validation de configuration au démarrage

### Authentification

-   ✅ Tokens JWT avec expiration
-   ✅ Validation des credentials via API Oddo
-   ✅ Middleware de protection des routes

### Gestion d'erreurs

-   ✅ Pas d'exposition d'informations sensibles en production
-   ✅ Logs d'erreurs sécurisés
-   ✅ Timeouts configurés pour les appels API

## 🐛 Debug et Logs

### Mode développement

```bash
APP_ENV=development
APP_DEBUG=true
```

### Logs PHP

Les logs sont disponibles dans :

-   Docker : `docker logs <container_name>`
-   Local : Fichiers de log PHP du système

### Tests d'API

```bash
# Test de santé
curl http://localhost:8000/status

# Test d'authentification
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"user": "demo", "pass": "demo"}'

# Test des comptes (avec JWT)
curl -X GET http://localhost:8000/accounts \
  -H "Authorization: Bearer <your_jwt_token>"
```

## 🚀 Déploiement Production

### 1. Configuration production

```bash
# .env pour production
APP_ENV=production
APP_DEBUG=false
ODDO_BASE_URI=https://production-api.oddo.com/
JWT_SECRET=<strong-production-secret>
STORAGE_DRIVER=redis
```

### 2. Optimisations

```bash
# Installation sans dev dependencies
composer install --no-dev --optimize-autoloader

# Cache d'autoloader optimisé
composer dump-autoload --optimize --no-dev
```

### 3. Docker en production

```bash
# Build optimisé
docker build -f php/Dockerfile -t oddoslim-api:prod .

# Déploiement avec variables d'environnement
docker run -d \
  --name oddoslim-api \
  -p 80:80 \
  -e ODDO_BASE_URI="https://prod-api.oddo.com/" \
  -e JWT_SECRET="<production-secret>" \
  -e APP_ENV="production" \
  -v /var/app/storage:/var/www/html/storage \
  oddoslim-api:prod
```

## 📈 Monitoring

### Métriques recommandées

-   Temps de réponse API
-   Taux d'erreur d'authentification
-   Utilisation du cache
-   Disponibilité de l'API Oddo

### Endpoint de santé

```http
GET /status
```

Retourne l'état de l'application et sa version.

## 🤝 Contribution

### Standards de code

-   PSR-12 pour le style de code
-   PHPStan niveau 8 pour l'analyse statique
-   Tests unitaires requis

### Workflow

1. Fork le projet
2. Créer une branche feature
3. Commits avec messages clairs
4. Tests et vérifications
5. Pull Request avec description

## 📞 Support

### Issues communes

**JWT secret manquant**

```bash
# Erreur: JWT_SECRET is required
# Solution:
echo "JWT_SECRET=$(openssl rand -base64 32)" >> .env
```

**API Oddo inaccessible**

```bash
# Erreur: Timeout ou 404
# Solution: Vérifier ODDO_BASE_URI ou utiliser le mode mock
```

**Cache non fonctionnel**

```bash
# Erreur: Permission denied sur storage/
# Solution:
chmod 755 storage/
chown www-data:www-data storage/
```

### Logs de debug

```bash
# Activer les logs détaillés
APP_DEBUG=true

# Consulter les logs en temps réel
tail -f /var/log/nginx/error.log
# ou
docker logs -f <container_name>
```

---

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🔗 Liens Utiles

-   [Slim Framework](https://www.slimframework.com/)
-   [PHP-DI Documentation](https://php-di.org/)
-   [JWT.io](https://jwt.io/)
-   [Docker Documentation](https://docs.docker.com/)
