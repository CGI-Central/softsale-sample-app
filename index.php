<?php
/**
 * That is a sample apllication designed to show how 
 * aMember SoftSale module can be used in real PHP apps
 * to handle license keys, activations and "call-home"
 */
ini_set('display_errors', true);
error_reporting(E_ALL);

define('API_URL', 'http://alex.localhost.int/amember40/softsale/api');
define('DATA_DIR', __DIR__ . '/data');
define('RANDOM_KEY', 'da39a3ee5e6b4b0d3255bfef95601890afd80709'); //that is how activation cache will be encrypted - CHANGE IT!

function askVerifyAndSaveLicenseKey()
{
    if (empty($_POST['key']))
    {
        renderLicenseForm('Please enter a license key'); 
        exit();
    } else {
        $license_key = preg_replace('/[^A-Za-z0-9-_]/', '', trim($_POST['key'])); 
    }
    $checker = new Am_LicenseChecker($license_key, API_URL, RANDOM_KEY, null);
    if (!$checker->checkLicenseKey()) // license key not confirmed by remote server
    {
        renderLicenseForm($checker->getMessage()); 
        exit();
    } else { // license key verified! save it into the file
        file_put_contents(DATA_DIR . '/key.txt', $license_key);
        return $license_key;
    }
}

function renderLicenseForm($errorMsg = null)
{
    echo <<<CUT
<html>
<head><title>License Key</title></head>
<body>
    <div style='color:red; font-weight:bold;'>$errorMsg</div>
    <form method='post'>
        <label>
        Enter License Key: 
        <input type="text" name="key">
        </label>
        <input type="submit" value="Verify">
    </form>
</body></html>
CUT;
}

if (!is_writeable($fn = DATA_DIR . '/key.txt'))
    exit("Please chmod file [$fn] to 666");
if (!is_writeable($fn = DATA_DIR . '/activation-cache.txt'))
    exit("Please chmod file [$fn] to 666");

// normally you put config reading and bootstraping before license checking
//  --- there should be your application bootstrapping code 
// this example just does not need any bootstrap

// in a real application, the license key and activation cache must be stored into
// a database 
// here we store it into files to keep things clear
require_once __DIR__ . '/vendor/Am/LicenseChecker.php';

$license_key = trim(file_get_contents(DATA_DIR  . '/key.txt'));
if (!strlen($license_key)) // we have no saved key? so we need to ask it and verify it
{
    $license_key = askVerifyAndSaveLicenseKey();
}

// now second, optional stage - check activation and binding of application
$activation_cache = trim(file_get_contents(DATA_DIR . '/activation-cache.txt'));
$prev_activation_cache = $activation_cache; // store previous value to detect change
$checker = new Am_LicenseChecker($license_key, API_URL, RANDOM_KEY, null);
$ret = empty($activation_cache) ?
           $checker->activate($activation_cache) : // explictly bind license to new installation
           $checker->checkActivation($activation_cache); // just check activation for subscription expriation, etc.
           
// in any case we need to store results to avoid repeative calls to remote api
if ($prev_activation_cache != $activation_cache)
    file_put_contents(DATA_DIR . '/activation-cache.txt', $activation_cache);

if (!$ret)
    exit("Activation failed: (" . $checker->getCode() . ') ' . $checker->getMessage());

/// now your script may continue and do normal functionality
/// in this case it will be traditional code :)
echo "<h1>Hello World!</h1><p>I am a licensed and activated script, and I'm ready to do my job.</p>";
