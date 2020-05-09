
StatIC-WeeWX
============

## Préambule

**Description**  
Ce script permet de produire un fichier texte contenant les dernières données de votre station météo, afin de l'intégrer au réseau StatIC de l'association Infoclimat. Ce script est fait pour les stations fonctionnant sous le logiciel WeeWX, sur une base de données SQLite ou MySQL. Le réseau StatIC d'Infoclimat accepte à ce jour les stations Davis Instruments Vantage Pro 2 et Vantage Vue.

**Système d'unités utilisé**  
Le script est capable de trouver automatiquement le système d'unités utilisé dans la BDD, et convertit les valeurs si nécessaires en système métrique.
Il fonctionne avec les deux principaux types de bases de données supportés par WeeWX : SQLite (BDD par défaut) et MySQL.

**Envoi FTP vers www.infoclimat.fr**  
Si le script détecte que le dernier enregistrement disponible en BDD est âgé de plus de 20 minutes, alors le fichier n'est pas envoyé sur le FTP d'Infoclimat.
En clair, si WeeWX s'arrête pour une raison quelconque, le script arrête également d'envoyer le fichier à Infoclimat puisqu'il est identique.

**Moyenne angulaire pour la direction du vent**  
La direction moyenne du vent sur 10 minutes nécessite de faire une moyenne angulaire, et non une moyenne "traditionnelle". 
La fonction utilisée pour la moyenne d'angles est inspirée de : https://gist.github.com/carloscabo/96e70853f1bb6b6a4d33e4c5c90c6cbb

## Requis
* Une station météo fonctionnant déjà avec Weewx
	* Cette installation de WeeWX (sur un Raspberry Pi, ou autre) peut stocker les données dans une base de données SQLite ou MySQL
	* Cette installation de WeeWX peut utiliser n'importe quel système d'unités, le script détectera l'unité et fera les conversions nécessaires
* Un accès en ligne de commande à votre Raspberry Pi. Si vous avez installé WeeWX ce ne devrait pas être un souci
* Un accès FTP sur Infoclimat (en faire la demande explicite lors de la demande d'intégration au réseau StatIC - les identifiants vous sont ensuite fournis par l'équipe)


## Installation
### Installation de git et php
Git est un logiciel permettant de cloner rapidement les deux fichiers nécessaires au fonctionnement de ce script.
PHP (php-cli dans notre cas) va permettre d'exécuter le script.
Il peut également être nécéssaire d'intaller le paquet ``php-sqlite3`` si vous utilisez une base de données SQLite sur votre instance de WeeWX
```
sudo apt update && sudo apt install git php-cli
## Si utilisation de SQLite, ajouter :
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

## Mise à jour du script

Ce script peut subir des modifications, visant à apporter des correctifs de bugs, ou des améliorations pour une meilleure intégration de vos données sur le site Infoclimat.

Dans ce cas, si le script est modifié après que vous l'ayez installé, il faudra suivre la procédure ci-après pour profiter des dernières mises à jour.
Cette manipulation permet de conserver une copie du fichier de configuration ``config.php``.

Il faudra adapter les commandes suivantes à votre configuration (emplacement du script).

Se déplacer dans le répertoire parent du script :
```
cd /home/pi/
```

Puis déplacer l'actuel dossier vers un autre répertoire (dans le but de ne pas perdre le fichier de configuration) :
```
mv StatIC-WeeWX StatIC-WeeWX.old
```

Récupération de la nouvelle version du script :
```
git clone https://github.com/AssociationInfoclimat/StatIC-WeeWX.git
```

Copie de la sauvegarde du fichier de configuration ``config.php`` dans le nouveau répertoire :
```
cp StatIC-WeeWX.old/config.php StatIC-WeeWX/config.php
```

C'est tout, le script est de nouveau fonctionnel !


## Changelog

* V2.0 - 2018.11.01
	* Fusion des scripts pour SQLite et MySQL
	* Arrondi des UV et rayonnement solaire
	* Ajout de la fonction de détection auto de l'unité pour la partie MySQL
	* Ajout du cumul horaire pluie_cumul_1h

* V2.1 - 2019.02.13
	* Correctif pour l'UV qui était *trop* arrondi

* V2.2 - 2019.06.09
	* Correctif opérateur de comparaison (remplacement des ``==`` par ``===``)
	* Correctif du problème de vent lorsqu'égal à 0, qui était pris comme NULL au lieu de direction Nord

* V2.3 - 2019.06.13
	* Correctif sur les requetes SQL pour constituer un tableau associatif plutôt qu'un index numérique (remplacement des ``$row[1]`` par ``$row['nomDeLaColonne']``) => plus propre
	* Ajout de la procédure de MAJ dans le README

* V2.4 - 2019.11.05
	* Correctif sur le tableau associatif pour la version SQLite --> la version 2.3 du script renvoyait systématiquement une erreur en utilisation avec SQLite.

* V2.5 - 2020-05-09
	* Les calculs sur 10 minutes/1heure, etc. (>= $start AND <= $stop) n'incluent maintenant plus le $stop (strictement inférieur (<) à $stop). **Evite la double comptabilisation d'un basculement d'auget intevenu par exemple à xxh00 ou xxh10 etc.** (merci @Tempétueux98 et d'autres sur le forum IC pour le signalement)
	* Correctif sur la direction du vent moyen. **Ne renvoi maintenant plus de valeur si la vitesse moyenne est égale à 0**. (merci @Tempétueux98 sur le forum IC pour le signalement)