StatIC-WeeWX
============

## Préambule
 Script permettant de générer un fichier texte pour le réseau StatIC de l'association Infoclimat pour une intégration à leur réseau d'une station météo (Davis VP2, Vue, ou Oregon WMR300) fonctionnant sous le logiciel WeeWX sur une base de données SQLite ou MySQL.

 Le script est capable de trouver automatiquement le système d'unité utilisé dans la BDD, et convertit les valeurs si nécessaire.

 La fonction utilisée pour la moyenne d'angles est inspirée de : https://gist.github.com/carloscabo/96e70853f1bb6b6a4d33e4c5c90c6cbb

## Requis
* Une station météo fonctionnant déjà avec Weewx
	* Cette installation de WeeWX (sur un Raspberry Pi, au autre) peut stocker les données dans une base de données SQLite ou MySQL


## Installation
### Installation de git et php
Git est un logiciel permettant de cloner rapidement les deux fichiers nécessaires au fonctionnement de ce script.
PHP (php-cli dans notre cas) va permettre d'exécuter le script.
```
sudo apt update && sudo apt install git php-cli
```
### Copie des fichiers
Se placer dans un premier temps dans le répertoire ou l'on veut copier le script, puis cloner le répertoire
```
cd /home/pi/
git clone https://github.com/AssociationInfoclimat/StatIC-WeeWX.git
```
### Configuration
On peut maintenant se placer dans le répertoire du script afin de modifier le fichier de configuration
```
cd StatIC-WeeWX
nano config.php
```

Le premier choix à faire est le type de base de données utilisé par votre instance de WeeWX.
Tous les paramètres sont commentés directement dans le fichier.

> **:exclamation: Attention :exclamation:**
> Ne pas modifier le fichier ``static.php``, toute la configuration se trouve dans le script ``config.php``


## Test du script
Pour lancer le script en ligne de commande sans le mode debug :
```
php /home/pi/StatIC-WeeWX/static.php --debug=false
```

Pour lancer le script en ligne de commande et avec le mode debug :
```
php /home/pi/StatIC-WeeWX/static.php --debug=true
```

## Automatisation

Pour automatiser le script dans un cron.
Edition de la crontab :
```
crontab -e
```
Puis ajouter :
```
*/10 * * * * sleep 45 && php /home/scripts/static.php
```

## Changelog

* V2.0 - 2018.11.01
	* Fusion des scripts pour SQLite et MySQL
	* Arrondi des UV et rayonnement solaire
	* Ajout de la fonction de détection auto de l'unité pour la partie MySQL
	* Ajout du cumul horaire pluie_cumul_1h