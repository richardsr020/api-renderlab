# 🎬 **RenderLab — Backend API**

**RenderLab** est une plateforme **SaaS** qui aide les créateurs de contenus à transformer facilement leurs scripts narratifs en **vidéos cinématographiques** prêtes pour les réseaux sociaux.
Le backend est construit avec **Symfony + API Platform** et s’appuie sur l’IA pour :

* Analyser le script fourni par l’utilisateur
* Détecter les scènes clés
* Générer des prompts visuels détaillés pour chaque scène
* Créer automatiquement des images cinématographiques avec plusieurs angles de caméra
* Fournir un workflow simplifié pour la post-production.

---

## ✅ **Fonctionnalité principale**

1. **Soumission de script**
   L’utilisateur écrit ou colle son script narratif dans l’application.

2. **Analyse IA**
   Pour chaque nouveau script arrive dans la base de donnee,Le backend envoie ce script à un modèle IA (LLM) pour détecter les scènes, les personnages et les ambiances.

3. **Génération de prompts**
   Pour chaque scène détectée, l’IA génère **2 à 4 prompts** visuels différents, chacun correspondant à un angle de caméra différent (ex. plan large, gros plan, contre-plongée).

4. **Création des images**
   Ces prompts sont ensuite envoyés à un modèle IA de génération d’images (ex. Midjourney, DALL·E, Stable Diffusion) pour produire les visuels.
   Chaque image générée est stockée **côté serveur** pour une durée **limitée à 30 jours** maximum.

5. **Stockage éphémère et lien sécurisé**
   Les images sont hébergées via un stockage type **S3** ou **Cloudflare R2**, avec expiration automatique après 1 mois.
   L’utilisateur peut télécharger ses images à tout moment.

6. **Système de crédits**
   Chaque appel IA consomme des crédits. Les transactions sont enregistrées pour un suivi précis et une facturation juste.

---

## ✅ **Structure de la base de données**

| Entité                | Description                                                                               |
| --------------------- | ----------------------------------------------------------------------------------------- |
| **User**              | Gestion des comptes, rôles, crédits eta connexion JWT.                                     |
| **Script**            | Contient le script original de l’utilisateur et le JSON complet de l’analyse IA.          |
| **Prompt**            | Contient chaque prompt généré pour une scène + l’URL de l’image + date d’expiration.      |
| **Job**               | Suit chaque tâche technique IA (ex. analyse de script ou génération d’image) et son état. |
| **CreditTransaction** | Historique de consommation ou recharge de crédits par utilisateur.                        |

**Relations clés :**

* Un **User** possède plusieurs **Scripts**
* Un **Script** possède plusieurs **Prompts**
* Un **Script** est lié à plusieurs **Jobs**
* Un **User** a un historique de **CreditTransactions**

---

## ✅ **Bonnes pratiques**

* 🔒 **Sécurité** : Authentification JWT, CORS configuré pour Next.js
* 💾 **Stockage** : Images hébergées sur un bucket cloud avec expiration à 30 jours
* ⚙️ **Purge automatique** : Cron Symfony pour supprimer les fichiers expirés
* 📊 **Traçabilité** : Chaque action IA consomme des crédits enregistrés pour facturation.

---

## ✅ **Tech Stack**

| Côté         | Stack                                                                     |
| ------------ | ------------------------------------------------------------------------- |
| **Backend**  | Symfony, API Platform, Doctrine ORM, JWT Auth                             |
| **IA**       | OpenAI / LLM pour l’analyse script + IA images (Midjourney, DALL·E, SDXL) |
| **Stockage** | S3 / Cloudflare R2 via Flysystem                                          |
| **Frontend** | Next.js (hors scope de ce repo)                                           |

---

## ✅ **Exemple de JSON renvoyé par l’IA**

```json
{
  "scriptId": "123e4567-e89b-12d3-a456-426614174000",
  "scenes": [
    {
      "sceneNumber": 1,
      "description": "A dusty attic with warm sunlight.",
      "prompts": [
        "Dusty attic, wide shot, warm sunlight through window.",
        "Dusty attic, close-up on old chest, cinematic lighting."
      ]
    },
    {
      "sceneNumber": 2,
      "description": "Hero enters the room cautiously.",
      "prompts": [
        "Young hero enters room, over-the-shoulder shot, suspenseful.",
        "Close-up on hero's nervous face, dramatic shadows."
      ]
    }
  ]
}
```

---

## ✅ **Durée de rétention**

* Les images générées restent disponibles pendant **30 jours** maximum.
* Au-delà, elles sont automatiquement supprimées du stockage pour optimiser les coûts.
* Les utilisateurs Premium peuvent bénéficier d’un stockage plus long.

---

## ✅ **Pourquoi c’est structuré ainsi ?**

✔️ Traçabilité complète des scripts, prompts et images
✔️ Coût de stockage maîtrisé grâce à l’expiration automatique
✔️ Flexibilité pour offrir des fonctionnalités premium (stockage illimité, versionning, historique)
✔️ Sécurité et contrôle des accès grâce à JWT et URLs sécurisées

---

# API Documentation

## Authentication

### Register
- **POST** `/api/register`
- **Body:**
```json
{
  "email": "user@example.com",
  "password": "yourPassword"
}
```
- **Response:**
  - 201 Created, `{ "message": "User registered successfully." }`

### Login
- **POST** `/api/login`
- **Body:**
```json
{
  "email": "user@example.com",
  "password": "yourPassword"
}
```
- **Response:**
  - 200 OK, `{ "token": "...", "refresh_token": "..." }`

### Refresh Token
- **POST** `/api/token/refresh`
- **Body:**
```json
{
  "refresh_token": "..."
}
```
- **Response:**
  - 200 OK, `{ "token": "...", "refresh_token": "..." }`

---

## Script CRUD

> All routes below require `Authorization: Bearer <token>` header.

### List Scripts
- **GET** `/api/scripts`
- **Response:**
```json
[
  {
    "id": 1,
    "title": "...",
    "rawText": "...",
    "status": "...",
    "author": 1,
    "createdAt": "...",
    "updatedAt": "..."
  },
  ...
]
```

### Create Script
- **POST** `/api/scripts`
- **Body:**
```json
{
  "title": "My Script",
  "rawText": "Script content...",
  "status": "draft"
}
```
- **Response:**
  - 201 Created, script object

### Show Script
- **GET** `/api/scripts/{id}`
- **Response:**
  - 200 OK, script object

### Update Script
- **PUT/PATCH** `/api/scripts/{id}`
- **Body:** (any updatable field)
```json
{
  "title": "Updated Title",
  "rawText": "Updated content...",
  "status": "published"
}
```
- **Response:**
  - 200 OK, updated script object
- **Note:** Only the author can update.

### Delete Script
- **DELETE** `/api/scripts/{id}`
- **Response:**
  - 204 No Content
- **Note:** Only the author can delete.

### Analyze Script (AI)
- **POST** `/api/scripts/{id}/analyze`
- **Headers:** `Authorization: Bearer <token>`
- **Response:**
```json
{
  "scriptId": 1,
  "scenes": [
    {
      "sceneNumber": 1,
      "description": "A sample scene description.",
      "prompts": [
        "Prompt 1 for scene 1",
        "Prompt 2 for scene 1"
      ]
    },
    ...
  ]
}
```
- **Note:** Only the author can analyze their script. The result is stored in the script's `aiAnalysis` field.

---

## Error Responses
- 401 Unauthorized: Missing or invalid JWT
- 403 Forbidden: Not the author (for update/delete)
- 409 Conflict: Email already in use (register)
- 400 Bad Request: Missing required fields

---

## 🏆 **Tu peux copier ce README pour ton repo RenderLab — Backend**

Si tu veux, je peux aussi te générer :

* Un **exemple de fichier `schema.sql` complet**
* Une version **MakerBundle : toutes les commandes**
* Un **diagramme ERD**

Dis-moi ! Je suis prêt ! 🚀

