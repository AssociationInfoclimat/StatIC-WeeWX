<?php

/*
 *
 * Script permettant de générer un fichier texte pour le réseau StatIC de l'association Infoclimat
 * pour une intégration à leur réseau d'une station météo (Davis VP2) fonctionnant sous le logiciel
 * WEEWX sur une base de données SQLite ou MySQL.
 *
 * Le sript trouve automatiquement le système d'unité utilisé dans la BDD, et convertit les valeurs si
 * nécéssaires
 *
 * Repris modifié et adapté pour l'association Infoclimat
 * https://www.infoclimat.fr/stations/static.php
 *
 * Le détail du fonctionnement de ce script se trouve sur GitHub : https://github.com/AssociationInfoclimat/StatIC-WeeWX
 *
 */


require_once "config.php";


// VERSION - NE PAS MODIFIER !!
	$version = "weewx-".$db_type."-2.4";

// Fuseau horaire du script
	$datetimeNow = date('Y-m-d H:i:s');
	date_default_timezone_set($timezone);

// DEBUG
	$options = getopt(null, array(
		'debug:',
	));
	$debug = $options['debug'];




// START SQLITE
if ($db_type === "sqlite") {

// Connection à la BDD SQLite WeeWX
	$db_handle = new SQLite3($db_file);

	$query_string = "SELECT * FROM $db_table_sqlite ORDER BY dateTime DESC LIMIT 1;";
	$db_handle->exec($query_string);
	$result = $db_handle->query($query_string);
	$row = $result->fetchArray(SQLITE3_ASSOC);

// Détermination du système d'unité utilisé dans la BDD
// 1 = US ; 16 = METRIC ; 17 = METRICWX
	$unit=$row['usUnits'];
	if ($debug=="true") {
		echo "Version script : ".$version.PHP_EOL;
		echo "Unite BDD : ".$unit.PHP_EOL.PHP_EOL;
	}

// Établissement des timestamp stop et start
	$stop=$row['dateTime'];
	$minutes10=$stop-(600);
	$minutes60=$stop-(3600);

// Établissement de la date et de l'heure du dernier relevé dispo en BDD
	$date=gmdate('d/m/Y',$stop);
	$heure=gmdate('H\hi',$stop);

// DEBUG heure
if ($debug=="true") {
	echo "Timezone : ".$timezone.PHP_EOL;
	$datetimeNowTimezone = date('Y-m-d H:i:s');
	echo "Datetime : ".$datetimeNow.PHP_EOL;
	echo "Datetime Timezone : ".$datetimeNowTimezone.PHP_EOL;
	$timestampNowTimezone = time();
	echo "TS Timezone : ".$timestampNowTimezone.PHP_EOL.PHP_EOL;
	echo "TS WeeWX BDD STOP : ".$stop.PHP_EOL;
	echo "Heure (gmdate) WeeWX BDD : ".$heure.PHP_EOL;
}


/*
 *
 * Mise en forme et calcul des paramètres temps réel
 *
 */

// temperature
	if ($row['outTemp'] === null){
		$temp = '';
	}else{
		if ($unit=='1') {
			$temp = round(($row['outTemp']-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$temp = round($row['outTemp'],1);
		}
	}

// pression
	if ($row['barometer'] === null){
		$pression = '';
	}else{
		if ($unit=='1') {
			$pression = round($row['barometer']*33.8639,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$pression = round($row['barometer'],1);
		}
	}

// hygro
	if ($row['outHumidity'] === null){
		$hygro = '';
	}else{
		$hygro = round($row['outHumidity'],1);
	}

// dewpoint
	if($row['dewpoint'] === null){
		$dewpoint = '';
	}else{
		if ($unit=='1') {
			$dewpoint = round(($row['dewpoint']-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$dewpoint = round($row['dewpoint'],1);
		}
	}

// intensite pluie
	if ($row['rainRate'] === null){
		$rainrate = '';
	}else{
		if ($unit=='1') {
			$rainrate = round($row['rainRate']*25.4,1);
		}elseif ($unit=='16') {
			$rainrate = round($row['rainRate']*10,1);
		}elseif ($unit=='17') {
			$rainrate = round($row['rainRate'],1);
		}
	}

// rayonnement solaire
	if ($row['radiation'] === null){
		$solar = '';
	}else{
		$solar = round($row['radiation'],0);
	}

// rayonnement UV
	if($row['UV'] === null){
		$uv = '';
	}else{
		$uv = round($row['UV'],1);
	}

/*
 * Calcul des moyennes pour le vent sur 10 minutes
 */

// Récupération et calcul de la vitesse moyenne du vent moyen des 10 dernières minutes
	$sql = "SELECT AVG(windSpeed) FROM $db_table_sqlite WHERE dateTime >= '$minutes10' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	if($row[0] === null){
		$avg_wind_10 = '';
	}else{
		$avg_wind_10 = round($row[0],1);
		if ($unit=='1') {
			$avg_wind_10 = round($row[0]*1.60934,1);
		}elseif ($unit=='16') {
			$avg_wind_10 = round($row[0],1);
		}elseif ($unit=='17') {
			$avg_wind_10 = round($row[0]*3.6,1);
		}
	}

// Récupération et calcul de la moyenne de la direction du vent sur 10 minutes
// Dans un premier temps, on définit une fonction qui permettra de calculer
// la moyenne de plusieurs angles en degrées
	function mean_of_angles( $angles, $degrees = true ) {
		if ( $degrees ) {
			$angles = array_map("deg2rad", $angles); // Convert to radians
		}
		$s_ = 0;
		$c_ = 0;
		$len = count( $angles );
		for ($i = 0; $i < $len; $i++) {
			$s_ += sin( $angles[$i] );
			$c_ += cos( $angles[$i] );
		}
		// $s_ /= $len;
		// $c_ /= $len;
		$mean = atan2( $s_, $c_ );
		if ( $degrees ) {
			$mean = rad2deg( $mean ); // Convert to degrees
		}
		if ($mean < 0) {
			$mean_ok = $mean + 360;
		} else {
			$mean_ok = $mean;
		}
		return $mean_ok;
	}

// Récupération des valeurs de direction du vent moyen des 10 dernières minutes
// Requete + mise en tableau de la réponse
	$windDirArray = array();
	$res = $db_handle->query("SELECT windDir FROM $db_table_sqlite WHERE dateTime >= '$minutes10' AND dateTime <= '$stop'");
	while ($row=$res->fetchArray(SQLITE3_ASSOC)) {
		if (!is_null ($row['windDir'])) {
			$windDirArray[] = $row['windDir'];
		}
	}

// Calcul de la moyenne avec la fonction `mean_of_angles` et le tableau
	$avg_windDir_10_check = mean_of_angles($windDirArray);

// Vérification du résultat et arrondi
	if($avg_windDir_10_check === null){
		$avg_windDir_10 = '';
	}else{
		$avg_windDir_10 = round($avg_windDir_10_check,1);
	}

// Détermination de la rafale max des 10 dernières minutes
	$sql = "SELECT max(windGust) FROM $db_table_sqlite WHERE dateTime >= '$minutes10' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$max_windGust_10_check = $row[0];
	if($max_windGust_10_check === null){
		$max_windGust_10 = '';
	}else{
		if ($unit=='1') {
			$max_windGust_10 = round($max_windGust_10_check*1.60934,1);
		}elseif ($unit=='16') {
			$max_windGust_10 = round($max_windGust_10_check,1);
		}elseif ($unit=='17') {
			$max_windGust_10 = round($max_windGust_10_check*3.6,1);
		}
	}


/*
 * Intensité de pluie sur l'heure
 */

// Intensite pluie max sur 1 heure
	$sql = "SELECT max(rainRate) FROM $db_table_sqlite WHERE dateTime >= '$minutes60' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$max_rainRate_hour_check = $row[0];
	if($max_rainRate_hour_check === null){
		$max_rainRate_hour = '';
	}else{
		if ($unit=='1') {
			$max_rainRate_hour = round($max_rainRate_hour_check*25.4,1);
		}elseif ($unit=='16') {
			$max_rainRate_hour = round($max_rainRate_hour_check*10,1);
		}elseif ($unit=='17') {
			$max_rainRate_hour = round($max_rainRate_hour_check,1);
		}
	}



/*
 *
 * Mise en forme et calcul des paramètres temps passé
 *
 */

// Cumul pluie sur l'heure glissante
	$sql = "SELECT sum(rain) FROM $db_table_sqlite WHERE dateTime >= '$minutes60' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$cumul_rain_hour_check = $row[0];
	if($cumul_rain_hour_check === null){
		$cumul_rain_hour = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_hour = round($cumul_rain_hour_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_hour = round($cumul_rain_hour_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_hour = round($cumul_rain_hour_check,1);
		}
	}

// Cumul pluie de la journée
	$today = strtotime('today midnight');
	$sql = "SELECT sum(rain) FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$cumul_rain_today_check = $row[0];
	if($cumul_rain_today_check === null){
		$cumul_rain_today = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_today = round($cumul_rain_today_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_today = round($cumul_rain_today_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_today = round($cumul_rain_today_check,1);
		}
	}

// DEBUG today midnight
	if ($debug=="true") {
		$dateTimeToday = date('d-m-Y H:i:s',$today);
		echo PHP_EOL;
		echo "Datetime Today midnight : ".$dateTimeToday.PHP_EOL;
		echo "TS Today midnight : ".$today.PHP_EOL;
	}

// Cumul pluie de l'année
	$yearNow = date('Y');
	$startYear = strtotime('01-01-'.$yearNow);
	$sql = "SELECT sum(rain) FROM $db_table_sqlite WHERE dateTime >= '$startYear' AND dateTime <= '$stop';";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$cumul_rain_year_check = $row[0];
	if($cumul_rain_year_check === null){
		$cumul_rain_year = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_year = round($cumul_rain_year_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_year = round($cumul_rain_year_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_year = round($cumul_rain_year_check,1);
		}
	}

// Intensite pluie max de la journée
	$sql = "SELECT dateTime, rainRate FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop' AND rainRate = (SELECT MAX(rainRate) FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$max_rainRate_today_check = $row[1];

	if($max_rainRate_today_check === null){
		$max_rainRate_today = '';
		$max_rainRate_todayTime = '';
	}else{
		if ($unit=='1') {
			$max_rainRate_today = round($max_rainRate_today_check*25.4,1);
		}elseif ($unit=='16') {
			$max_rainRate_today = round($max_rainRate_today_check*10,1);
		}elseif ($unit=='17') {
			$max_rainRate_today = round($max_rainRate_today_check,1);
		}
		$max_rainRate_todayTime = gmdate('H\hi',$row[0]);
	}

// Max temp de la journée (fausse Tx puisque de 0h à 24h)
	$sql = "SELECT dateTime, outTemp FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop' AND outTemp = (SELECT MAX(outTemp) FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$max_temp_today_check = $row[1];

	if($max_temp_today_check === null){
		$max_temp_today = '';
		$max_temp_todayTime = '';
	}else{
		if ($unit=='1') {
			$max_temp_today = round(($max_temp_today_check-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$max_temp_today = round($max_temp_today_check,1);
		}
		$max_temp_todayTime = gmdate('H\hi',$row[0]);
	}

// Min temp de la journée (fausse Tn puisque de 0h à 24h)
	$sql = "SELECT dateTime, outTemp FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop' AND outTemp = (SELECT MIN(outTemp) FROM $db_table_sqlite WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $db_handle->query($sql);
	$row = $res->fetchArray();
	$min_temp_today_check = $row[1];

	if($min_temp_today_check === null){
		$min_temp_today = '';
		$min_temp_todayTime = '';
	}else{
		if ($unit=='1') {
			$min_temp_today = round(($min_temp_today_check-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$min_temp_today = round($min_temp_today_check,1);
		}
		$min_temp_todayTime = gmdate('H\hi',$row[0]);
	}


}
// END SQLITE

// START MYSQL
elseif ($db_type === "mysql") {

// Connection à la BDD WeeWX
	$con = mysqli_connect($db_host,$db_user,$db_pass,$db_name);

// Récupération du dernier relevé dispo en BDD
	$sql = "SELECT * FROM $db_name.$db_table_mysql ORDER BY dateTime DESC LIMIT 1;";
	$res= $con->query($sql);
	$row = mysqli_fetch_assoc($res);

// Détermination du système d'unité utilisé dans la BDD
// 1 = US ; 16 = METRIC ; 17 = METRICWX
	$unit=$row['usUnits'];
	if ($debug=="true") {
		echo "Version script : ".$version.PHP_EOL;
		echo "Unite BDD : ".$unit.PHP_EOL.PHP_EOL;
	}

// Établissement des timestamp stop et start
	$stop=$row['dateTime'];
	$minutes10=$stop-(600);
	$minutes60=$stop-(3600);

// Établissement de la date et de l'heure du dernier relevé dispo en BDD
	$date=date('d/m/Y',$stop);
	$heure=date('H\hi',$stop);

// DEBUG heure
if ($debug=="true") {
	echo "Timezone : ".$timezone.PHP_EOL;
	$datetimeNowTimezone = date('Y-m-d H:i:s');
	echo "Datetime : ".$datetimeNow.PHP_EOL;
	echo "Datetime Timezone : ".$datetimeNowTimezone.PHP_EOL;
	$timestampNowTimezone = time();
	echo "TS Timezone : ".$timestampNowTimezone.PHP_EOL.PHP_EOL;
	echo "TS WeeWX BDD STOP : ".$stop.PHP_EOL;
	echo "Heure (gmdate) WeeWX BDD : ".$heure.PHP_EOL;
}

/*
 *
 * Mise en forme et calcul des paramètres temps réel
 *
 */

// temperature
	if ($row['outTemp'] === null){
		$temp = '';
	}else{
		if ($unit=='1') {
			$temp = round(($row['outTemp']-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$temp = round($row['outTemp'],1);
		}
	}

// pression
	if ($row['barometer'] === null){
		$pression = '';
	}else{
		if ($unit=='1') {
			$pression = round($row['barometer']*33.8639,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$pression = round($row['barometer'],1);
		}
	}

// hygro
	if ($row['outHumidity'] === null){
		$hygro = '';
	}else{
		$hygro = round($row['outHumidity'],1);
	}

// dewpoint
	if($row['dewpoint'] === null){
		$dewpoint = '';
	}else{
		if ($unit=='1') {
			$dewpoint = round(($row['dewpoint']-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$dewpoint = round($row['dewpoint'],1);
		}
	}

// intensite pluie
	if ($row['rainRate'] === null){
		$rainrate = '';
	}else{
		if ($unit=='1') {
			$rainrate = round($row['rainRate']*25.4,1);
		}elseif ($unit=='16') {
			$rainrate = round($row['rainRate']*10,1);
		}elseif ($unit=='17') {
			$rainrate = round($row['rainRate'],1);
		}
	}

// rayonnement solaire
	if ($row['radiation'] === null){
		$solar = '';
	}else{
		$solar = round($row['radiation'],0);
	}

// rayonnement UV
	if($row['UV'] === null){
		$uv = '';
	}else{
		$uv = round($row['UV'],1);
	}


// Récupération et calcul de la vitesse moyenne du vent moyen des 10 dernières minutes
	$sql = "SELECT AVG(windSpeed) FROM $db_name.$db_table_mysql WHERE dateTime >= '$minutes10' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	if($row[0] === null){
		$avg_wind_10 = '';
	}else{
		$avg_wind_10 = round($row[0],1);
		if ($unit=='1') {
			$avg_wind_10 = round($row[0]*1.60934,1);
		}elseif ($unit=='16') {
			$avg_wind_10 = round($row[0],1);
		}elseif ($unit=='17') {
			$avg_wind_10 = round($row[0]*3.6,1);
		}
	}

// Récupération et calcul de la moyenne de la direction du vent sur 10 minutes
// Dans un premier temps, on définit une fonction qui permettra de calculer
// la moyenne de plusieurs angles en degrées
	function mean_of_angles( $angles, $degrees = true ) {
		if ( $degrees ) {
			$angles = array_map("deg2rad", $angles); // Convert to radians
		}
		$s_ = 0;
		$c_ = 0;
		$len = count( $angles );
		for ($i = 0; $i < $len; $i++) {
			$s_ += sin( $angles[$i] );
			$c_ += cos( $angles[$i] );
		}
		// $s_ /= $len;
		// $c_ /= $len;
		$mean = atan2( $s_, $c_ );
		if ( $degrees ) {
			$mean = rad2deg( $mean ); // Convert to degrees
		}
		if ($mean < 0) {
			$mean_ok = $mean + 360;
		} else {
			$mean_ok = $mean;
		}
		return $mean_ok;
	}

// Récupération des valeurs de direction du vent moyen des 10 dernières minutes
	$sql = "SELECT windDir FROM $db_name.$db_table_mysql WHERE dateTime >= '$minutes10' AND dateTime <= '$stop';";

// Requete + mise en tableau de la réponse
	$windDirArray = array();
	foreach ($con->query($sql) as $row) {
		if (!is_null ($row['windDir'])) {
			$windDirArray[] = $row['windDir'];
		}
	}

// Calcul de la moyenne avec la fonction `mean_of_angles` et le tableau
	$avg_windDir_10_check = mean_of_angles($windDirArray);

// Vérification du résultat et arrondi
	if($avg_windDir_10_check === null){
		$avg_windDir_10 = '';
	}else{
		$avg_windDir_10 = round($avg_windDir_10_check,1);
	}

// Détermination de la rafale max des 10 dernières minutes
	$sql = "SELECT max(windGust) FROM $db_name.$db_table_mysql WHERE dateTime >= '$minutes10' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$max_windGust_10_check = $row[0];
	if($max_windGust_10_check === null){
		$max_windGust_10 = '';
	}else{
		if ($unit=='1') {
			$max_windGust_10 = round($max_windGust_10_check*1.60934,1);
		}elseif ($unit=='16') {
			$max_windGust_10 = round($max_windGust_10_check,1);
		}elseif ($unit=='17') {
			$max_windGust_10 = round($max_windGust_10_check*3.6,1);
		}
	}


/*
 * Intensité de pluie sur l'heure
 */

// Intensite pluie max sur 1 heure
	$sql = "SELECT max(rainRate) FROM $db_name.$db_table_mysql WHERE dateTime >= '$minutes60' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$max_rainRate_hour_check = $row[0];
	if($max_rainRate_hour_check === null){
		$max_rainRate_hour = '';
	}else{
		if ($unit=='1') {
			$max_rainRate_hour = round($max_rainRate_hour_check*25.4,1);
		}elseif ($unit=='16') {
			$max_rainRate_hour = round($max_rainRate_hour_check*10,1);
		}elseif ($unit=='17') {
			$max_rainRate_hour = round($max_rainRate_hour_check,1);
		}
	}



/*
 *
 * Mise en forme et calcul des paramètres temps passé
 *
 */

// Cumul pluie sur l'heure glissante
	$sql = "SELECT SUM(rain) FROM $db_table_mysql WHERE dateTime >= '$minutes60' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$cumul_rain_hour_check = $row[0];
	if($cumul_rain_hour_check === null){
		$cumul_rain_hour = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_hour = round($cumul_rain_hour_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_hour = round($cumul_rain_hour_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_hour = round($cumul_rain_hour_check,1);
		}
	}


// Cumul pluie de la journée
	$today = strtotime('today midnight');
	$sql = "SELECT SUM(rain) FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$cumul_rain_today_check = $row[0];
	if($cumul_rain_today_check === null){
		$cumul_rain_today = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_today = round($cumul_rain_today_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_today = round($cumul_rain_today_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_today = round($cumul_rain_today_check,1);
		}
	}

// DEBUG today midnight
	if ($debug=="true") {
		$dateTimeToday = date('d-m-Y H:i:s',$today);
		echo PHP_EOL;
		echo "Datetime Today midnight : ".$dateTimeToday.PHP_EOL;
		echo "TS Today midnight : ".$today.PHP_EOL;
	}

// Cumul pluie de l'année
	$yearNow = date('Y');
	$startYear = strtotime('01-01-'.$yearNow);
	$sql = "SELECT SUM(rain) FROM $db_name.$db_table_mysql WHERE dateTime >= '$startYear' AND dateTime <= '$stop';";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$cumul_rain_year_check = $row[0];
	if($cumul_rain_year_check === null){
		$cumul_rain_year = '';
	}else{
		if ($unit=='1') {
			$cumul_rain_year = round($cumul_rain_year_check*25.4,1);
		}elseif ($unit=='16') {
			$cumul_rain_year = round($cumul_rain_year_check*10,1);
		}elseif ($unit=='17') {
			$cumul_rain_year = round($cumul_rain_year_check,1);
		}
	}

// Intensite pluie max de la journée
	$sql = "SELECT dateTime, rainRate FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop' AND rainRate = (SELECT MAX(rainRate) FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$max_rainRate_today_check = $row[1];

	if($max_rainRate_today_check === null){
		$max_rainRate_today = '';
		$max_rainRate_todayTime = '';
	}else{
		if ($unit=='1') {
			$max_rainRate_today = round($max_rainRate_today_check*25.4,1);
		}elseif ($unit=='16') {
			$max_rainRate_today = round($max_rainRate_today_check*10,1);
		}elseif ($unit=='17') {
			$max_rainRate_today = round($max_rainRate_today_check,1);
		}
		$max_rainRate_todayTime = gmdate('H\hi',$row[0]);
	}

// Max temp de la journée (fausse Tx puisque de 0h à 24h)
	$sql = "SELECT dateTime, outTemp FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop' AND outTemp = (SELECT MAX(outTemp) FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$max_temp_today_check = $row[1];

	if($max_temp_today_check === null){
		$max_temp_today = '';
		$max_temp_todayTime = '';
	}else{
		if ($unit=='1') {
			$max_temp_today = round(($max_temp_today_check-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$max_temp_today = round($max_temp_today_check,1);
		}
		$max_temp_todayTime = gmdate('H\hi',$row[0]);
	}

// Min temp de la journée (fausse Tn puisque de 0h à 24h)
	$sql = "SELECT dateTime, outTemp FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop' AND outTemp = (SELECT MIN(outTemp) FROM $db_name.$db_table_mysql WHERE dateTime >= '$today' AND dateTime <= '$stop');";
	$res = $con->query($sql);
	$row = mysqli_fetch_row($res);
	$min_temp_today_check = $row[1];

	if($min_temp_today_check === null){
		$min_temp_today = '';
		$min_temp_todayTime = '';
	}else{
		if ($unit=='1') {
			$min_temp_today = round(($min_temp_today_check-32)/1.8,1);
		}elseif ($unit=='16' OR $unit=='17') {
			$min_temp_today = round($min_temp_today_check,1);
		}
		$min_temp_todayTime = gmdate('H\hi',$row[0]);
	}
}
// END MYSQL
else {
	echo "Le paramètre db_type est mal renseigné".PHP_EOL;
	exit();
}

/*
 *
 * Génération du fichier texte
 *
 */

	$static=
	"# INFORMATIONS\n"
	."id_station=$id_station\n"
	."type=txt\n"
	."version=$version\n"
	."date_releve=$date\n"
	."heure_releve_utc=$heure\n"
	."# PARAMETRES TEMPS REEL\n"
	."temperature=$temp\n"
	."pression=$pression\n"
	."humidite=$hygro\n"
	."point_de_rosee=$dewpoint\n"
	."vent_dir_moy=$avg_windDir_10\n"
	."vent_moyen=$avg_wind_10\n"
	."vent_rafales=$max_windGust_10\n"
	."pluie_intensite=$rainrate\n"
	."pluie_intensite_maxi_1h=$max_rainRate_hour\n"
	."# PARAMETRES TEMPS PASSE\n"
	."pluie_cumul_1h=$cumul_rain_hour\n"
	."pluie_cumul=$cumul_rain_today\n"
	."pluie_cumul_heure_utc=$heure\n"
	."pluie_cumul_annee=$cumul_rain_year\n"
	."pluie_intensite_maxi=$max_rainRate_today\n"
	."pluie_intensite_maxi_heure_utc=$max_rainRate_todayTime\n"
	."tn_heure_utc=$min_temp_todayTime\n"
	."tn_deg_c=$min_temp_today\n"
	."tx_heure_utc=$max_temp_todayTime\n"
	."tx_deg_c=$max_temp_today\n"
	."# ENSOLEILLEMENT\n"
	."radiations_solaires_wlk=$solar\n"
	."uv_wlk=$uv\n";

// Enregistrement du fichier en local
	$file = $folder."/StatIC_".$id_station.".txt";
	$fp=fopen($file,'w');
	fwrite($fp,$static);
	fclose($fp);



// Push du fichier sur le serveur Infoclimat
// SEULEMENT SI LE TIMESTAMP DU DERNIER RELEVE EN BDD A MOINS DE 20 MINUTES
	$timestampNow = time();
	if ($debug=="true") {
		echo PHP_EOL;
		echo "TS Now Comparo FTP IC : ".$timestampNow.PHP_EOL;
	}
	if ($timestampNow - $stop < 1200) {
		$conn_id = ftp_connect($ftp_server) or die("could not connect to $ftp_server");
		if (!@ftp_login($conn_id, $ftp_username, $ftp_password)) { die("could not connect to infoclimat");}
		$remote="StatIC_".$id_station.".txt";
		ftp_put($conn_id, $remote, $file, FTP_ASCII);
		ftp_close($conn_id);
	}

?>
