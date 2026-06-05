# Architecture HaloGari

Ce document sert de convention pour la refonte progressive de l'application.

## Backend Symfony

`src/Controller/`
: Controleurs HTTP classiques. Ils doivent rester fins : lecture de requete, securite, appel a un service, rendu Twig ou JSON.

`src/Controller/Api/`
: API REST pour la PWA et la future app mobile. Les endpoints doivent retourner des payloads stables et simples.

`src/Application/`
: Logique applicative reutilisable par le web, l'API et les taches asynchrones.

`src/Application/Trajet/`
: Cas d'usage autour des trajets : recherche, publication, format API/mobile.

`src/Message/` et `src/MessageHandler/`
: Taches asynchrones Symfony Messenger. Les actions longues ne doivent pas rester dans les controleurs.

`src/Service/`
: Services techniques ou integrations externes : Stripe, Push, Meta/Facebook, generation d'affiche, notifications.

## Twig

`templates/base.html.twig`
: Coque globale de l'application.

`templates/components/layout/`
: Composants de structure : navigation, barre mobile, shell.

`templates/components/auth/`
: Composants d'authentification reutilisables.

`templates/components/ride/`
: Composants lies aux trajets : cartes de resultats, listes, details.

`templates/partials/`
: Compatibilite avec les anciennes pages. Les nouveaux composants doivent aller dans `templates/components/`.

`templates/<feature>/`
: Pages par domaine fonctionnel : `trajet`, `user`, `reservation`, `paiement`, etc.

## Assets publics

`public/assets/styles/app.css`
: Design system HaloGari moderne : tokens, layout, composants, animations, responsive.

`public/assets/scripts/app-ui.js`
: Micro-interactions globales : reveal au scroll, etats de pression, effets de navigation.

`public/css/style.css`
: Ancien CSS historique. A conserver pendant la migration, puis reduire progressivement.

`public/js/`
: Scripts historiques ou fonctionnels existants : villages, datepicker, PWA, notifications, formulaires.

## Regles pour la refonte page par page

1. Garder les controleurs fins.
2. Mettre la logique partagee dans `src/Application/<Domaine>/`.
3. Creer les nouveaux morceaux visuels dans `templates/components/`.
4. Eviter de mettre du CSS inline dans les templates.
5. Ajouter les styles reutilisables dans `public/assets/styles/app.css`.
6. Garder `partials/` seulement pour compatibilite.
7. Verifier apres chaque page : Twig, PHP si besoin, cache Symfony, puis rendu navigateur.
