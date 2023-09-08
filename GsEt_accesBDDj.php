<?php
// Fichier    : GsEt_accesBDDj.php
// Appelé par : appel ajax depuis diverses pages
// Suivi de   : néant
// Fonction   : retourne un tableau (HTML ou JSON) en fonction des paramètres envoyés
// Auteur     : B.Perreaud
// Date création  : 12 fév. 2021
// Derniére modif : 

/* Modifications
    .. ..... .. - BP  : - 
    
*/

/* Variables passées depuis le fichier appelant
     nom de la var.   : méthode : type   :   rôle de la var.
     -----------------:---------:--------:--------------------------------------
     champs           : POST    :        : liste des champs à récupérer
	 table1           : POST    :        : nom de la table1 à lire
	 table2           : POST    :        : nom de la table2 à lire
	 jointure12       : POST    :        : condition de jointure t1-t2
     filtre           : POST    :        : filtre à appliquer (clause WHERE)
     tri              : POST    :        : tri à appliquer (clause ORDER BY)
     typeRetour       : POST    :        : type de retour attendu : "tableHtml" ou "json"
     
     exemple d'appel : 
        $.ajax({
            url : 'GsEt_accesBDDj.php',
            type : 'POST',
            async: false,
            data : {table1:etudiant, table2:inscritDans, jointure12:"numEtud=_numEtud", champs:[listeChamp], filtre:filtre, tri:order, typeRetour:"json"}, 
            success : function(result){
                tEtudiant = JSON.parse(result);
            }
        });

*/
session_start();

// insertion des constantes des droits et celles liees a la base de donnees
include("GsEt_constantes_metier.inc");  // contient la définition des niveaux d'accès prédéfinis
include("GsEt_constantes_bdd_PDO.inc"); // contient la définition des variables : $dns, $user, $pass. 

$NiveauAccesNecessaire = $NiveauAccesDelete;

$NomUtilisateur=$_SESSION["NomUtilisateur"];
$MotDePasseUtilisateur = $_SESSION["MotDePasseUtilisateur"] ;
$NumeroUtilisateur = $_SESSION["NumeroUtilisateur"];
$NiveauAccesUtilisateur = $_SESSION["NiveauAccesUtilisateur"] ;

//if ( ($NiveauAccesUtilisateur & $NiveauAccesNecessaire) == $NiveauAccesNecessaire ){
    
if ( $NiveauAccesUtilisateur >= 0 ){

    // tentative de connexion
    try {
        $options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'); 
        $DataBaseConnexion = new PDO($dns, $user, $pass, $options);
        $DataBaseConnexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    catch (PDOException $e) {
        echo "Erreur !: " . $e->getMessage() . "<br/>";
        die();
    }

    $loc_fr = setlocale(LC_ALL,"fra","fr_FR","fr_FR.UTF8","fr.UTF8"); // sous Windows il faut 'fra' ou 'french' et sous Linux 'fr_FR'.

    extract($_POST);
    //print_r($_POST);

    if (isset($table1) && $table1==$tables['utilisateur']){
        $table1="";
    }
    if (isset($table1) && $table1!="" && isset($table2) && $table2!="" && isset($jointure12) && $jointure12!="" && isset($champs)){
        
        if (isset($filtre) && $filtre!=""){
            $clauseWhere = " WHERE " . $filtre;
        }else{
            $clauseWhere = " ";
        }

        if (isset($tri) && $tri!=""){
            $clauseOrderBy = " ORDER BY " . $tri;
        }else{
            $clauseOrderBy = "";
        }
        
        $listeDesChamps="";
        foreach ($champs as $valeur){
            $listeDesChamps.=$valeur . ", ";
        }
        $listeDesChamps = substr($listeDesChamps,0,strlen($listeDesChamps)-2 );
        
        $sql="SELECT " . $listeDesChamps . " FROM " . $table1 . " INNER JOIN " . $table2 . " ON " . $jointure12 .$clauseWhere . $clauseOrderBy;
        //echo "<br>".$sql."<br>";

        $DataBaseRequete = $DataBaseConnexion->prepare($sql);

        try {
            $DataBaseRequete->execute();
        }
        catch(PDOException $e){
            echo "Erreur ! : " . $e->getMessage() . "<br>";
            die();
        }
        $NombreDeLigne=$DataBaseRequete->rowCount();
        $Nb_Champs = $DataBaseRequete->columnCount();
        $tValeurs = $DataBaseRequete->fetchAll(PDO::FETCH_ASSOC);

        if ($typeRetour=="tableHtml"){
            // récupération des noms des colonnes
            for ($i=0 ; $i<$Nb_Champs ; $i++){
                $t = $DataBaseRequete->getColumnMeta($i);
                $tListeChamps[$i] = $t['name'];
                // rmq : on est obligé de procéder en 2 temps avec getColumnMeta,
                //       l'instruction "getColumnMeta($i)['name']" ne fonctionne pas !
            }

            $i=1;
            echo "<table border='1' align='center' id='tabloliste'>";
            echo "<tr>\n";
            echo "<th>&nbsp;</p>";
            foreach ($tListeChamps as $valeur){
                echo "<th><b>",$valeur,"</b></th>\n";
            }
            echo "</tr>\n";

            foreach ($tValeurs as $tValeur){
                echo "<tr>\n";
                echo "<td style='text-align:right;'>" . $i . "</td>";
                $i+=1;
                foreach ($tValeur as $valeur){
                    echo "<td>",$valeur,"</td>\n";
                }
                echo "</tr>\n";
            }
        }elseif ($typeRetour=="json"){
             echo json_encode($tValeurs);
        }
    } else{
        echo "néant !!!";
    }
}else{
    echo "<p>Accès NON autorisé !!!</p>";
}
?>
