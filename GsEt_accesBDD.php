<?php
// Fichier    : GsEt_accesBDD.php
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
     nom de la var.   : méthode     : type   :   rôle de la var.
     -----------------:-------------:--------:--------------------------------------
	 table            : POST        :        : nom de la table à lire
     champs           : POST        :        : tableau des champs à récupérer
     filtre           : POST        :        : filtre à appliquer (clause WHERE sans WHERE)
     tri              : POST        :        : tri à appliquer (clause ORDER BY)
     typeRetour       : POST ou GET :        : type de retour attendu : "tableHtml" ou "json"
     groupBy          : POST        :        : regroupement à appliquer (clause GROUP BY)
     
     tableDemandee    : GET         :        : table demandée lors de l'appel ajax de autocomplete de jquery 
                                                pour l'input ville 
     champDemande     : GET         :        : champ demandé lors de l'appel ajax de autocomplete de jquery 
                                                pour l'input ville 
     autoComplet      : GET         :        : table demandée lors de l'appel ajax de autocomplete de jquery 
                                                pour l'input ville 
     term             : GET         :        : contient le début de la ville lors de l'appel ajax de autocomplete de jquery 
                                                pour l'input ville. Cette variable est automatiquement ajoutée par jQuery
                                                lors de l'apple pour l'autocomplete
                                                
Appel POST se fait avec :   data : {table:"inscritDans", champs:['chp1', 'chp2'], filtre:"chp=val", tri:"chp ASC", typeRetour:"json|tableHtml"},

Appel GET : GsEt_accesBDD.php?tableDemandee=listeVilles&typeRetour=json&autoComplet=true&champDemande=ville&term=saint-j

*/
session_start();

// insertion des constantes des droits et celles liees a la base de donnees
include("GsEt_constantes_metier.inc");  // contient la définition des niveaux d'accès prédéfinis
include("GsEt_constantes_bdd_PDO.inc"); // contient la définition des variables : $dns, $user, $pass. 

$NiveauAccesNecessaire = $NiveauAccesDelete;

if(isset($_SESSION["NomUtilisateur"])){
    $NomUtilisateur = $_SESSION["NomUtilisateur"];
    $MotDePasseUtilisateur = $_SESSION["MotDePasseUtilisateur"] ;
    $NumeroUtilisateur = $_SESSION["NumeroUtilisateur"];
    $NiveauAccesUtilisateur = $_SESSION["NiveauAccesUtilisateur"] ;
}else{
    $NiveauAccesUtilisateur = 0;
}
//if ( ($NiveauAccesUtilisateur & $NiveauAccesNecessaire) == $NiveauAccesNecessaire ){
    
if ( $NiveauAccesUtilisateur >= 0 ){

    // tentative de connexion
    try {
        $options = array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'); 
        //echo $_SERVER['PHP_SELF']."\n";
        $DataBaseConnexion = new PDO($dns, $user, $pass, $options);
        $DataBaseConnexion->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
    catch (PDOException $e) {
        echo "Erreur !: " . $e->getMessage() . "<br/>";
        die();
    }

    $loc_fr = setlocale(LC_ALL,"fra","fr_FR","fr_FR.UTF8","fr.UTF8"); // sous Windows il faut 'fra' ou 'french' et sous Linux 'fr_FR'.

    //print_r($_POST);
    //print_r(count($_GET));
    
    if (isset($_POST) && count($_POST)!=0){
        extract($_POST);
    }
    
    if (isset($_GET) && count($_GET)!=0){
        extract($_GET);
        $table = $tableDemandee;
    }

    if (isset($table) && $table=="utilisateur"){
        $table="";
    }
    if (isset($table) && $table!=""){
        
        if (isset($filtre) && $filtre!=""){
            $clauseWhere = " WHERE " . $filtre;
        }else{
            $clauseWhere = " ";
        }

        if (strtolower($typeRetour)=="json" && isset($autoComplet) && $autoComplet=="true"){
            $term = $_GET['term'];
            $clauseWhere = " WHERE " . $champDemande . " LIKE '" . $term . "%'";
        }

        if (isset($tri) && $tri!=""){
            $clauseOrderBy = " ORDER BY " . $tri;
        }else{
            $clauseOrderBy = "";
        }

        if (isset($groupBy) && $groupBy!=""){
            $clauseGroupBy = " GROUP BY " . $groupBy;
        }else{
            $clauseGroupBy = "";
        }

        if (isset($champs)){
            $listeDesChamps="";
            if(is_array($champs)){
                foreach ($champs as $valeur){
                    $listeDesChamps.=$valeur . ", ";
                }
                $listeDesChamps = substr($listeDesChamps,0,strlen($listeDesChamps)-2 );
            } else{
                $listeDesChamps=$champs;
            }
            
        }else{
            $listeDesChamps="*";
        }
        $sql="SELECT " . $listeDesChamps . " FROM " . $table . $clauseWhere . $clauseGroupBy . $clauseOrderBy;
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
        
        /*
        echo "nb de lignes : " . $NombreDeLigne . "<br>";
        echo "nb de champs : " . $Nb_Champs . "<br>";
        //print_r($tValeurs);
        echo "type de retour : " . $typeRetour . " " . gettype($typeRetour) . "<br>";
        echo (strtolower($typeRetour) == "tablehtml")*1;
        */
        if (strtolower($typeRetour) == "tablehtml"){
            // récupération des noms des colonnes
            for ($i=0 ; $i<$Nb_Champs ; $i++){
                $t = $DataBaseRequete->getColumnMeta($i);
                $tListeChamps[$i] = $t['name'];
                // rmq : on est obligé de procéder en 2 temps avec getColumnMeta,
                //       l'instruction "getColumnMeta($i)['name']" ne fonctionne pas !
            }

            $i=1;
            echo "<table border='1' align='center' id='tabloliste'>";
            echo "<thead><tr>\n";
            echo "<th>&nbsp;</p>";
            foreach ($tListeChamps as $valeur){
                echo "<th><b>",$valeur,"</b></th>\n";
            }
            echo "</tr></thead>\n";
            
            echo"<tbody>";
            foreach ($tValeurs as $tValeur){
                echo "<tr>\n";
                echo "<td style='text-align:right;'>" . $i . "</td>";
                $i+=1;
                foreach ($tValeur as $valeur){
                    echo "<td>",$valeur,"</td>\n";
                }
                echo "</tr>\n";
            }
            echo"</tbody>";

        }elseif (strtolower($typeRetour)=="json"){
            if (isset($autoComplet) && $autoComplet=="true"){
                $array = array(); // on créé le tableau (vide)
                //print_r($tValeurs);
                foreach ($tValeurs as $tValeur){ // on effectue une boucle pour obtenir les données
                    array_push($array, $tValeur[$champDemande]); // et on ajoute celles-ci à notre tableau
                }
                echo json_encode($array); // il n'y a plus qu'à convertir en JSON
            }else{
                echo json_encode($tValeurs);
            }
        }
    } else{
        echo "néant !!!";
    }
}else{
    echo "<p>Accès NON autorisé !!!</p>";
}
?>
