# XML Advanced Electronic Signatures Document

## Installation
composer.json
```json
{
    "require": {
      "bigbank/php-xades-document" : "0.1"
    }
}
```

## Example code

### Create ADoc file for preparing
```php
<?php
use Bigbank\XadesDocument\ADoc;

$adoc = new ADoc();
$adoc->addDocument('agreement.pdf', 'this-document-content...');
$adoc->addAuthor('John Smith', 'Viru 4, Tallinn, Estonia', '10201');
$adoc->save('prepare.adoc');
```

### Open ADoc file and put signature

```php
<?php
use Bigbank\XadesDocument\ADoc;

$adoc = new ADoc('prepare.adoc');
$adoc->addPrepareSignature('x509-certificate-content...');
$requestSignatureHash = $adoc->getLastSignatureRequestHash(true);
$privateKeyId = openssl_pkey_get_private("private_key.pem");
openssl_sign($requestSignatureHash, $signatureData, $privateKeyId);
$adoc->setLastSignatureData($signatureData);
$adoc->save('signed.adoc');
```

### Append ADoc
```php
<?php
use Bigbank\XadesDocument\ADoc;

$adoc = new ADoc('first.adoc');
$adoc->appendFileOrContent('second.adoc');
$adoc->save('appended.adoc');
```