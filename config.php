<?php

/*
 *
 * Script permettant de générer un fichier texte pour le réseau StatIC de l'association Infoclimat
 * pour une intégration à leur réseau d'une station météo (Davis VP2) fonctionnant sous le logiciel
 * WEEWX sur une base de données SQLite ou MySQL.
 *
 * Ce fichier permet la configuration du script.
 *
 * Le détail de son fonctionnement se trouve sur GitHub : https://github.com/AssociationInfoclimat/StatIC-WeeWX
 *
 */



/*
 * Mode SQLite ou MySQL ?
 */
	$db_type = "sqlite"; // deux valeurs possibles : sqlite ou mysql

/*
 * Emplacement de la BDD SQLite de WeeWX (si db_type = "sqlite")
 */
	$db_file = "/var/lib/weewx/weewx.sdb";
	$db_table_sqlite = "archive"; // Par défaut 'archive', ne modifier que si nécéssaire

/*
 * Parametres de connexion à la base de données WeeWX (si db_type = "mysql")
 */
	$db_host = 'localhost';
	$db_user = 'weewx';
	$db_pass = 'passe';
	$db_name = 'weewx';
	$db_table_mysql = 'archive';


/*
 * Timezone du RPI/de la station, à tester et controler le fichier de sortie (il doit être en UTC)
 * Possible de mettre : "UTC" ou "Europe/Paris" par exemple
 */
	$timezone = "UTC";

/*
 * Parametres FTP Infoclimat
 */
	$ftp_server = "ftp.infoclimat.fr";
	$ftp_username = "user";
	$ftp_password = "passe";

/*
 * Informations relatives à la station
 */
	// ID de la station qui servira de nom au fichier texte créé (le nom du fichier aura le préfixe "StatIC_" suivi de l'ID)
	$id_station = "station";
	// Emplacement ou sera enregistré le fichier texte créé
	$folder = "/var/www/html/IC/";

?>