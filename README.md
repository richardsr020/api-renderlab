# API RenderLab – Backend Symfony 7

**API RenderLab** est un backend SaaS moderne, sécurisé et extensible, construit avec Symfony 7 et orienté IA. Il permet à des utilisateurs de s’inscrire, de créer des scripts, puis de générer automatiquement des prompts, images (Gemini), et audio (Gemini) à partir de ces scripts. L’API est pensée pour un usage frontend (SPA, mobile, etc.) et pour une exploitation locale facile.

---

## Présentation

- **Technos** : Symfony 7, Doctrine, LexikJWT, API Platform (désactivé, tout est custom controller), HttpClient, ffmpeg, Groq, Gemini.
- **Sécurité** : Authentification JWT, contrôle d’accès strict, stockage sécurisé des médias.
- **Fonctionnalités principales** :
  - Inscription et login utilisateurs
  - CRUD sur les scripts
  - Génération de prompts IA (Groq)
  - Génération d’images IA (Gemini, JPG haute qualité)
  - Génération d’audio IA (Gemini, mp3 via ffmpeg)
  - Stockage et organisation des médias par script

---

## Endpoints API (pour le frontend)

### Authentification
- `POST /api/register`  — Inscription utilisateur
- `POST /api/login`     — Connexion utilisateur (retourne un JWT)

### Scripts
- `GET    /api/scripts`           — Lister les scripts de l’utilisateur
- `POST   /api/scripts`           — Créer un script
- `GET    /api/scripts/{id}`      — Voir un script
- `PATCH  /api/scripts/{id}`      — Modifier un script
- `DELETE /api/scripts/{id}`      — Supprimer un script

### Prompts
- `POST /api/scripts/{id}/get_prompts` — Générer et stocker les prompts IA pour un script

### Images
- `POST /api/scripts/{id}/generate_images` — Générer et stocker les images IA (JPG) pour un script

### Audio
- `POST /api/scripts/{id}/generate_audio` — Générer et stocker l’audio IA (mp3) pour un script

---

## Tester l’application en local

### Prérequis
- PHP >= 8.2
- Composer
- Symfony CLI (optionnel mais recommandé)
- ffmpeg (pour la conversion audio)
- Une base de données compatible Doctrine (ex : MySQL, PostgreSQL)

### Installation
1. **Cloner le repo**
   ```bash
   git clone <repo_url>
   cd api-renderlab
   ```
2. **Installer les dépendances**
   ```bash
   composer install
   ```
3. **Configurer l’environnement**
   - Copier `.env` en `.env.local` et adapter les variables (DB, JWT, GROQ_API_KEY, GEMINI_API_KEY)
4. **Générer la clé JWT**
   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```
5. **Créer la base et les tables**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```
6. **Lancer le serveur**
   ```bash
   symfony server:start
   # ou
   php -S 127.0.0.1:8000 -t public
   ```

### Tester les endpoints
- Utiliser Postman, Insomnia ou `curl`.
- Exemple d’inscription :
  ```bash
  curl -X POST http://127.0.0.1:8000/api/register -H "Content-Type: application/json" -d '{"email":"test@test.com","password":"motdepasse"}'
  ```
- Se connecter pour obtenir un JWT, puis l’utiliser dans les headers `Authorization: Bearer <token>` pour tous les endpoints protégés.

### Génération IA
- Les endpoints de génération (prompts, images, audio) nécessitent des clés Groq et Gemini valides dans `.env.local`.
- Les médias sont stockés dans `public/images/{script_id}/` et `public/audio/{script_id}/`.

---

## Contribution
- Forkez le repo, créez une branche, ouvrez une PR !
- Toute contribution (correction, nouvelle feature, doc) est la bienvenue.

---

## Support
Pour toute question, ouvrez une issue ou contactez l’équipe via le repo.

## copilote prompt
je t'explique : le script cree par l'utilisateur est stocke dans la base de donnee. puis tu vas creer des routes pour les controlleurs qui appartiennent  chacun au autres entite de sorte que  quand un script est deja cree, si l'utilisateur click sur get_prompt  un controlleur recoit cette requette, et via un service, il prend ce script selon son id et celui du user, le script est envoye vers le model IA Groq (la cle api est dans .env) groq va analyser les script, detecter  le moindre endroit ou un image est necessaire, il creer au moins trois prompts de generation d'images.  pourquoi trois prompts? parcequ'il faut plusieurs vues, face, dos, profile, et tants d'autres pour la meme scene. cette endroit. il va faire ce travail dans tout le script et renvoyer ces prompt puis symfony les recupere et les stockent dans la table prompt sous forme d'un array  a chaque scene, aumoin trois prompts d'images ou plus. et maintenant pour la generation de l'image, meme chose l'utilisateur apuie sur generate_images, le controleur approprie est active et via un service, contact l'AI de generation d'image GEMINI (la cle api est dans .env) et  les precedents prompts sont envoye pour onbtenir des image qui seront ensuit telecharge sur le serveur dans public/images dans un dossier ayant pour nom l'id du script, ou du user, ou de prompt  ca tu va decider de cela. et les noms des images generees sont stocke dans la table image  dans un array tout comme pour les prompt. pour l'audio c'est le meme process avec l'api de gemini  sauf qu'il n y aura qu'un seul audio en mp3 par script et selon la voix choisi par l'utilisateur.  tu doit optimiser ces systemes pour gagner en performance et ne pas surcharge l'api du model IA et optimiser le temp de reponsea