<?php

function migrer_saisies() {
    $migrer_articles = array(
        array(
            'saisie' => 'input',
            'options' => array(
                'nom' => 'articles',
                'label' => 'id_articles',
                'explication' => 'les identifiants sont séparés d\'une virgule',
                'obligatoire' => 'oui'
            )
        ),
        array(
            'saisie' => 'hidden',
            'options' => array(
                'nom' => 'id_parent',
                'valeur_forcee' => '2'
            )
        )
    );

    return $migrer_articles;
}


function formulaires_configurer_lh_migrer_charger_dist() {
    $valeurs = array(
        'articles' => '',
        'migrer_articles' => migrer_saisies()
    );

    return $valeurs;
}


function formulaires_configurer_lh_migrer_verifier_dist() {
    $erreurs = array();
    $obligatoires = array('articles');

    foreach ($obligatoires as $obligatoire) {
        if (!_request($obligatoire)) {
            $erreurs[$obligatoire] = _T('info_obligatoire');
        }
    }

    return $erreurs;
}

function formulaires_configurer_lh_migrer_traiter_dist() {
    $id_articles = explode(',', _request('articles'));

    foreach($id_articles as $id_article) {
        $article = sql_fetsel('*', 'spip_articles', 'id_article='.$id_article);
        $id_rubrique = $article['id_rubrique'];
        $id_secteur = $article['id_secteur'];

        $titre_rubrique = sql_fetsel('titre', 'spip_rubriques', 'id_rubrique='.$id_rubrique);

        // attribuer le titre de la rubrique à l'article et le déplacer au niveau du secteur.
        include_spip('action/editer_article');

        $champs = array('titre' => $titre_rubrique['titre'], 'id_parent' => $id_secteur);

        article_modifier($id_article, $champs);

        // Traiter les documents (oeuvres)
        // l'article qui contient les oeuvres est dans la même rubrique et le titre contient 02.
        $where_oeuvres = array(
            'id_rubrique='.$id_rubrique,
            'titre LIKE "02.%"'
        );

        $article_oeuvres = sql_fetsel('*', 'spip_articles', $where_oeuvres);
        $id_article_oeuvres = $article_oeuvres['id_article'];
        $statut_article_oeuvres = $article_oeuvres['statut'];

        // ajouter les oeuvres à l'article.
        if (isset($id_article_oeuvres)) {

            if ($statut_article_oeuvres == 'publie') {
                // on en profite pour dépublier l'article
                article_modifier($id_article_oeuvres, array('statut' => 'prop'));
            }

            migrer_documents_oeuvres($id_article_oeuvres['id_article'], $id_article);

        }
    }

    return array('message_ok' => 'Migration faite');
}


function migrer_documents_oeuvres($id_article_depart, $id_article_arrivee) {

    // les identifiants des oeuvres
    $id_documents = sql_allfetsel('id_document', 'spip_documents_liens', 'id_objet='.$id_article_depart);

    // le parent de l'oeuvre est un article
    $objet = 'article';
    $id_objet = $id_article_arrivee;

    include_spip('action/editer_document');

    foreach($id_documents as $id_document) {
        $id = $id_document['id_document'];

        // déclarer le parent du document "article|id_article"
        $champs = array('ajout_parents' => array("$objet|$id_objet"));

        document_modifier($id, $champs);
    }
}
