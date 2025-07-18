<?php
// @todo We should spin it into Jwt parent class and GoogleJwt child class.

declare(strict_types=1);

namespace Zolinga\Rms\Api\Google;

/**
 * 
 * This file contains the JWT parsed information.
 * The Jwt class is responsible for handling JSON Web Tokens (JWT) for authentication.
 * 
 * Data structure of the JWT token: 
 *  {
 *       "header": {
 *           "alg": "RS256",
 *           "kid": "ed8...fc4",
 *           "typ": "JWT"
 *       },
 *       "payload": {
 *           "iss": "https://accounts.google.com",
 *           "azp": "1....apps.googleusercontent.com",
 *           "aud": "1....apps.googleusercontent.com",
 *           "sub": 1.0896e+20,
 *           "hd": "...",
 *           "email": "...",
 *           "email_verified": true,
 *           "nbf": 1707944827,
 *           "name": "John Doe",
 *           "picture": "https://lh3.googleusercontent.com/a/ACg8o...=",
 *           "given_name": "John",
 *           "family_name": "Doe",
 *           "locale": "cs",
 *           "iat": 1707904187,
 *           "exp": 1707968787,
 *           "jti": "55...b788"
 *       },
 *       "signature": "US...NFg=="
 *   }
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @since 2024-04-22
 */
readonly class GoogleJwt implements \JsonSerializable
{
    public readonly string $jwt;
    public readonly array $header;
    public readonly array $payload;
    public readonly string $signature;

    /**
     * Decode the JWT token.
     */
    public function __construct(string $jwt)
    {
        $this->jwt = $jwt;

        $parts = explode(".", $jwt);
        $this->header = json_decode(base64_decode($parts[0]), true) or throw new \Exception("Invalid JWT header");
        $this->payload = json_decode(base64_decode($parts[1]), true) or throw new \Exception("Invalid JWT payload");
        $this->signature = base64_decode($parts[2]) or throw new \Exception("Invalid JWT signature");
    }

    /**
     * 
     * https://developers.google.com/identity/sign-in/web/backend-auth
     * 
     * To verify that the token is valid, ensure that the following criteria are satisfied:
     * 
     * The ID token is properly signed by Google. Use Google's public keys (available in JWK or PEM format) to verify the token's signature. These keys are regularly rotated; examine the Cache-Control header in the response to determine when you should retrieve them again.
     * The value of aud in the ID token is equal to one of your app's client IDs. This check is necessary to prevent ID tokens issued to a malicious app being used to access data about the same user on your app's backend server.
     * The value of iss in the ID token is equal to accounts.google.com or https://accounts.google.com.
     * The expiry time (exp) of the ID token has not passed.
     * If you need to validate that the ID token represents a Google Workspace or Cloud organization account, you can check the hd claim, which indicates the hosted domain of the user. This must be used when restricting access to a resource to only members of certain domains. The absence of this claim indicates that the account does not belong to a Google hosted domain.

     *
     * @return boolean
     */
    /**
     * @return bool
     */
    public function isValid(?string $googleClientId = null): bool
    {
        return
            $this->isValidIssuer() &&
            // @todo uncomment $this->isValidExpiration() && 
            $this->isValidAudience($googleClientId) &&
            $this->isValidSignature();
    }

    private function isValidAudience(?string $googleClientId = null): bool
    {
        if ($googleClientId && $this->payload['aud'] !== $googleClientId()) {
            trigger_error("Invalid audience (Google client id) in JWT: {$this->payload['aud']}, expected {$googleClientId}", E_USER_WARNING);
            return false;
        }
        return true;
    }

    private function isValidIssuer(): bool
    {
        $issuer = parse_url($this->payload['iss'], PHP_URL_HOST);
        if ($issuer !== 'accounts.google.com') {
            trigger_error("Invalid issuer in JWT: $issuer", E_USER_WARNING);
            return false;
        }
        return true;
    }

    private function isValidExpiration(): bool
    {
        if ($this->payload['exp'] < time()) {
            trigger_error("JWT has expired: {$this->payload['exp']} (" . date('c', $this->payload['exp']) . ")", E_USER_WARNING);
            return false;
        }
        return true;
    }

    // https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
    private function isValidSignature(): bool
    {
        // Following commented out block is the signature validation using official Google API client library available from composer.
        // The reason why we are not using it is that Composer pulls in dependencies comprising of 27k files/35MB! 
        // That is overkill for our use case. 
        // 
        // In case that logins are not working due to any changes in Google API, we can use this library as quick fall back solution before we fix our solution.
        //
        // To make it work run `composer require google/apiclient:^2.15.0` in ROOT/v2/ directory and uncomment the following block marked with 
        // GOOGLE API START and GOOGLE API END.
        //
        // // GOOGLE API START
        // if (!class_exists('\Google_Client')) {
        //     throw new \Exception("Google API client library not found. Please install it using composer require google/apiclient. Run composer require google/apiclient:^2.15.0 in ROOT/v2/ directory.");
        // }
        // $client = new \Google_Client(['client_id' => "10...o2l.apps.googleusercontent.com"]);  // Specify the CLIENT_ID of the app that accesses the backend
        // $payload = $client->verifyIdToken($this->jwt);
        // $ret = $payload ? true : false;
        // return $ret;
        // // GOOGLE API END

        // Verify the token's signature
        [$header, $payload, $signature] = explode(".", $this->jwt);
        $input = "{$header}.{$payload}";

        $pemCerts = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v1/certs'), true) or throw new \Exception("Unable to fetch Google public keys.");
        $cert = $pemCerts[$this->header['kid']]
            or throw new \Exception("Unable to find matching key for signature verification.");

        /** @var \OpenSSLAsymmetricKey $publicKey */
        $publicKey = openssl_pkey_get_public($cert)
            or throw new \Exception("Unable to parse public key " . json_encode($cert) . " for signature verification.");

        $signatureRaw = base64_decode($this->convertBase64UrlToBase64($signature));
        $ret = openssl_verify(
            $input,
            $signatureRaw,
            $publicKey,
            'SHA256'
        );
        // 1 - correct
        // 0 - incorrect signature
        // -1|false - error

        return $ret === 1;
    }

    public function jsonSerialize(): mixed
    {
        return [
            "header" => $this->header,
            "payload" => $this->payload,
            "signature" => base64_encode($this->signature) // binary, JSON does not support binary
        ];
    }

    /**
     * Convert a string in the base64url (URL-safe Base64) encoding to standard base64.
     *
     * @param string $input A Base64 encoded string with URL-safe characters (-_ and no padding)
     *
     * @return string A Base64 encoded string with standard characters (+/) and padding (=), when
     * needed.
     *
     * @see https://www.rfc-editor.org/rfc/rfc4648
     */
    private function convertBase64UrlToBase64(string $input): string
    {
        $remainder = \strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }
        return \strtr($input, '-_', '+/');
    }
}
