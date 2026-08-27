## Description

<!-- Ce que fait cette pull request, et pourquoi. Décrire l'intention, pas le diff. -->

## Ticket lié

<!-- Closes #123 -->

## Type de changement

- [ ] Correction d'anomalie (`type:anomalie`)
- [ ] Nouvelle fonctionnalité (`type:evolution`)
- [ ] Correctif de sécurité (`type:securite`)
- [ ] Dette technique, outillage, chaîne d'intégration (`type:technique`)
- [ ] Données personnelles (`type:rgpd`)
- [ ] Documentation

## Grille de relecture sécurité

À cocher par le relecteur. Une case non applicable se barre en le justifiant en
une ligne — elle ne se coche pas par défaut.

- [ ] **Les données reçues sont revalidées côté serveur.** Une validation
      JavaScript n'en est pas une : elle se contourne avec l'outil de
      développement du navigateur.
- [ ] **Toute nouvelle page est couverte par une règle de contrôle d'accès.**
      Vérifier `security.yaml` ou l'attribut `#[IsGranted]`. Une route ajoutée
      sans règle est publique.
- [ ] **Aucun mot de passe ni clé n'a été ajouté au code.** Y compris dans un
      commentaire, un test, un fichier de configuration ou une capture d'écran.
      Gitleaks contrôle l'arbre de travail, mais il ne connaît pas tous les
      formats.
- [ ] **Les actions sensibles sont journalisées** sur le canal `security` :
      connexion, échec d'authentification, changement de mot de passe, action
      d'administration. Les identifiants doivent être masqués.
- [ ] **La modification est accompagnée de tests.** Pour un correctif de
      sécurité, un test de non-régression : sans lui, rien n'empêche la faille de
      revenir.

## Vérifications

- [ ] La chaîne d'intégration est au vert
- [ ] `php bin/phpunit` passe en local
- [ ] Les migrations éventuelles ont été jouées et sont réversibles
