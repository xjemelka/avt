<?php

/* Společný soubor, který dále využijeme z ostatních skriptů, hodí se z několika
důvodů:
1) Připojení k DB je na jednom místě a při změně přihlašovacích údajů do DB se
    vše dá snadno změnit.
2) Připojení k DB už nebude nutné dále řešit v jiných skriptech.
3) Deklaruje autoloader, který zajistí načítání potřebných tříd šablonového
    systému.
4) Vytvoří instanci třídy šablonového systému
*/

// Zajistí zobrazení varování a připomínek PHP, což je užitečné při ladění programu.
error_reporting(E_ALL);

//K PRIHLASENI A NACTENI SESSION   
session_start(); //DO PROJEKTU PRIDELAT

// Připojení k databázi.
try {
    $db = new PDO('mysql:host=localhost;dbname=nastaveni', 'root', 'heslo2');
} catch (Exception $e) {
    echo "Nelze se připojit k databázi ".$e->getMessage();
    exit(); // Totéž jako die(); - ukončí běh PHP skriptu.
}

/* Nastavíme, že chyby při spuštění SQL dotazů budou ošetřeny výjimkami (není tedy nutné.
    testovat návratovou hodnotu funkce query()).
*/
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Základní PSR-0 autoloader zajistí automatické načítání souborů s deklarací tříd.
 * http://www.php-fig.org/psr/psr-0/
 */
function autoload($className)
{
    $className = ltrim($className, '\\');
    $fileName  = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    require $fileName;
}

// registrace funkce autoload() jako PHP autoloaderu
//-------spl_autoload_register('autoload');
// vložení skriptu pro lepší zobrazování chyb Latte
//--------require('Latte/exceptions.php');
require('latte.php');

// vytvoří se objekt třídy Latte\Engine() a uloží se do proměnné $tpl
$tpl = new Latte\Engine();

// inicializace pole pro proměnné šablony (pro pořádek)
$tplVars = array();