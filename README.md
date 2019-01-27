
StatIC-WeeWX
============

## Préambule

**Description**  
 Script permettant de générer un fichier texte pour le réseau StatIC de l'association Infoclimat pour une intégration à leur réseau d'une station météo (Davis VP2, Vue, ou Oregon WMR300) fonctionnant sous le logiciel WeeWX sur une base de données SQLite ou MySQL.

**Système d'unité utilisé**  
Le script est capable de trouver automatiquement le système d'unité utilisé dans la BDD, et convertit les valeurs si nécessaire en système métrique.
Il fonctionne avec les deux principaux types de base de données suppostés par WeeWX : SQLite (BDD par défaut) et MySQL.

**Envoi FTP vers www.infoclimat.fr**  
Si le script détecte que le dernier enregistrement disponible en BDD est agé de plus de 20 minutes, alors le fichier n'est pas envoyé sur le FTP d'Infoclimat.
En clair, si WeeWX s'arrète pour une raison quelconque, le script arrête également d'envoyer le fichier à Infoclimat puisqu'il est identique.

**Moyenne angulaire pour la direction du vent**  
La direction moyenne du vent sur 10 minutes nécéssite de faire une moyenne angulaire, et non une moyenne "traditionnelle". 
La fonction utilisée pour la moyenne d'angles est inspirée de : https://gist.github.com/carloscabo/96e70853f1bb6b6a4d33e4c5c90c6cbb

## Requis
* Une station météo fonctionnant déjà avec Weewx
	* Cette installation de WeeWX (sur un Raspberry Pi, ou autre) peut stocker les données dans une base de données SQLite ou MySQL
* Un accès en ligne de commande à votre Raspberry Pi. Si vous avez installé WeeWX ce ne devrait pas être un souci
* Un accès FTP sur Infoclimat (en faire la demande explicite lors de la demande d'intégration au réseau StatIC - les identifiants vous sont ensuite fournis par l'équipe)


## Installation
### Installation de git et php
Git est un logiciel permettant de cloner rapidement les deux fichiers nécessaires au fonctionnement de ce script.
PHP (php-cli dans notre cas) va permettre d'exécuter le script.
Il peut également être nécéssaire d'intaller le paquet ``php-sqlite3`` si vous utilisez une base de données SQLite sur votre instance de WeeWX
```
sudo apt update && sudo apt install git php-cli
## Facultatif :
sudo apt install php-sqlite3
```
### Copie des fichiers
Se placer dans un premier temps dans le répertoire ou l'on veut copier le script, puis cloner le répertoire
```
cd /home/pi/
git clone https://github.com/AssociationInfoclimat/StatIC-WeeWX.git
```
### Configuration
On peut maintenant se placer dans le répertoire du script afin de modifier le fichier de configuration.
```
cd StatIC-WeeWX
nano config.php
```
**Tous les paramètres sont commentés directement dans le fichier.**

> **:exclamation: Attention :exclamation:**
> Ne pas modifier le fichier ``static.php``, toute la configuration se trouve dans le script ``config.php``

**Type de base de données**
> Le premier choix à faire est le type de base de données utilisé par votre instance de WeeWX.
>
> Si vous utilisez une base de données SQLite, il faudra renseigner le paramètre de cette manière :
> ```
> $db_type = "sqlite";
> ```
> Si vous avez personnalisé votre instance de WeeWX pour pouvoir utiliser une base de données MySQL au lieu de SQLite, il faudra renseigner le paramètre de cette manière :
> ```
> $db_type = "mysql";
> ```

**Paramètres de connexion à la base de données**
> En fonction de votre choix précédent il va falloir renseigner différemment cette partie :
> * Si vous avez une base de données SQLite, il suffit d'indiquer l'emplacement du fichier SQLite, et le nom de la table principal. Pour ce dernier paramètre, il est probable que vous ne l'ayez pas changé et qu'il soit ``archive`` :
> ```
> $db_file = "/var/lib/weewx/weewx.sdb";
> $db_table_sqlite = "archive";
> ```
> L'emplacement de ce fichier peut varier en fonction de votre méthode d'installation de WeeWX. Référer vous à la documentation [d'installation de WeeWX](http://www.weewx.com/docs/usersguide.htm#installation_methods).
>
>
> * Si vous avez une base de données MySQL, il va falloir renseigner les paramètres de connexion à la base :
> ```
> $db_host = 'localhost';
> $db_user = 'weewx';
> $db_pass = 'passe';
> $db_name = 'weewx';
> $db_table_mysql = 'archive';
> ```
>
> * ``db_host`` : qui est l'adresse de l'hôte de la base de données. Probablement ``localhost`` si la base de données est hébergée sur votre Raspberry Pi
> * ``db_user`` : le nom d'utilisateur qui a accès à la BDD **en lecture seule de préférence** !
> * ``db_pass`` : le mot de passe de cet utilisateur ;
> * ``db_name`` : le nom de la base de données. Par défaut WeeWX la nomme ``weewx`` ;
> * ``db_table_mysql`` : Ici il s'agit du nom de la première table contenant tous les enregistrements. Par défaut WeeWX la nomme ``archive``.
>
> Vous pouvez renseigner les même paramètres de connexion que ceux de votre fichier de configuration de WeeWX, mais ce n'est pas recommandé car l'utilisateur ``weewx`` a les droits d'écritures sur la base. L'idéal est plutôt de créer un autre utilisateur avec seulement les droits de lecture sur la base de données (select).
> Cependant, cela fonctionnera aussi avec l'utilisateur ``weewx``.

**Timezone**
> A venir...

**FTP Infoclimat**
> Cette partie concerne la configuration de la connexion au FTP de l'association Infoclimat. Ces identifiants sont à demander directement à l'équipe lors de la demande d'intégration de votre station.
> ```
> $ftp_server = "ftp.infoclimat.fr";
> $ftp_username = "user";
> $ftp_password = "passe";
> ```

**Informations d'enregistrement du fichier**
> Cette dernière partie de la configuration concerne le répertoire d'enregistrement du fichier texte dans votre Raspberry Pi.
> Il faut dans un premier temps donner un "ID" à votre station, qui viendra compléter le nom du fichier.
> ```
> $id_station = "nice";
> ```
> Avec l'exemple ci-dessus, le nom du fichier sera : ``StatIC_nice``.
>
> Il faut maintenant renseigner le répertoire d'enregistrement :
> ```
> $folder = "/var/www/html/IC/";
> ```
> Vous pouvez ici renseigner n'importe quel répertoire **existant** sur votre Raspberry Pi.

## Test du script
Pour lancer le script en ligne de commande sans le mode debug :
```
php /home/pi/StatIC-WeeWX/static.php --debug=false
```

Pour lancer le script en ligne de commande et avec le mode debug (permettant notamment d'élucider certains problèmes liés au paramètre de timezone) :
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
*/10 * * * * sleep 45 && php /home/pi/StatIC-WeeWX/static.php --debug=false
```
``sleep 45`` permet d'attendre 45 secondes avant de lancer le script, afin de laisser le temps à WeeWX de procéder à l'enregistrement en base de données du dernier relevé.

## Changelog

* V2.0 - 2018.11.01
	* Fusion des scripts pour SQLite et MySQL
	* Arrondi des UV et rayonnement solaire
	* Ajout de la fonction de détection auto de l'unité pour la partie MySQL
	* Ajout du cumul horaire pluie_cumul_1h
