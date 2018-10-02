<?php
declare(strict_types=1);

namespace AirNMS;
require __DIR__."/../vendor/autoload.php";

use XeroPHP\Application\PrivateApplication;
use XeroPHP\Application\PublicApplication;
use XeroPHP\Remote\Request;
use XeroPHP\Remote\URL;

$config = array(
    "appType" => "public",
    "consumerKey" => "WRFEMS0POTFD7PQ8K3YY866PLHBXA6",
    "consumerSecret" => "ICRX1AYF58F4HRUHQS9KMNNQJ1VZK1",
    "callbackUrl" => "https://{no-domain-currently-set}"

);



// Start a session for the oauth session storage
session_start();


//These are the minimum settings - for more options, refer to examples/config.php
$config = [
    'oauth' => [
        "callback"        => "http://localhost/",
        "consumer_key"    => "WRFEMS0POTFD7PQ8K3YY866PLHBXA6",
        "consumer_secret" => "ICRX1AYF58F4HRUHQS9KMNNQJ1VZK1",
    ],
    "curl" => [
        CURLOPT_CAINFO => __DIR__."/../certs/ca-bundle.pem",
    ],
];

$xero = new PublicApplication($config);


//if no session or if it is expired
if (null === $oauth_session = getOAuthSession()) {
    $url = new URL($xero, URL::OAUTH_REQUEST_TOKEN);
    $request = new Request($xero, $url);

    //Here's where you'll see if your keys are valid.
    //You can catch a BadRequestException.
    try {
        $request->send();
    } catch (\Exception $e) {
        print_r($e);
        if ($request->getResponse()) {
            print_r($request->getResponse()->getOAuthResponse());
        }
    }

    $oauth_response = $request->getResponse()->getOAuthResponse();

    setOAuthSession(
        $oauth_response['oauth_token'],
        $oauth_response['oauth_token_secret']
    );

    printf(
        '<a href="%s">Click here to Authorize</a>',
        $xero->getAuthorizeURL($oauth_response['oauth_token'])
    );
    exit;
} else {
    $xero->getOAuthClient()
        ->setToken($oauth_session['token'])
        ->setTokenSecret($oauth_session['token_secret']);

    if (isset($_REQUEST['oauth_verifier'])) {
        $xero->getOAuthClient()->setVerifier($_REQUEST['oauth_verifier']);

        $url = new URL($xero, URL::OAUTH_ACCESS_TOKEN);
        $request = new Request($xero, $url);

        $request->send();
        $oauth_response = $request->getResponse()->getOAuthResponse();

        setOAuthSession(
            $oauth_response['oauth_token'],
            $oauth_response['oauth_token_secret'],
            $oauth_response['oauth_expires_in']
        );

        //drop the qs
        $uri_parts = explode('?', $_SERVER['REQUEST_URI']);

        //Just for demo purposes
        header(
            sprintf(
                'Location: http%s://%s%s',
                (isset($_SERVER['HTTPS']) ? 's' : ''),
                $_SERVER['HTTP_HOST'],
                $uri_parts[0]
            )
        );
        exit;
    }
}

use XeroPHP\Models\Accounting\Organisation;
use XeroPHP\Models\Accounting\Contact;

//Otherwise, you're in.
//print_r(json_encode($xero->load("Accounting\Contact")->execute()));
print_r(json_encode($xero->load("Accounting\Organisation")->execute()));






//The following two functions are just for a demo
//you should use a more robust mechanism of storing tokens than this!
function setOAuthSession($token, $secret, $expires = null)
{
    // expires sends back an int
    if ($expires !== null) {
        $expires = time() + intval($expires);
    }

    $_SESSION['oauth'] = [
        'token' => $token,
        'token_secret' => $secret,
        'expires' => $expires
    ];
}

function getOAuthSession()
{
    //If it doesn't exist or is expired, return null
    if (!isset($_SESSION['oauth'])
        || ($_SESSION['oauth']['expires'] !== null
            && $_SESSION['oauth']['expires'] <= time())
    ) {
        return null;
    }
    return $_SESSION['oauth'];
}