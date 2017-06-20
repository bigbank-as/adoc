<?php

namespace Bigbank\XadesDocument;

use Bigbank\XadesDocument\Exceptions\AlreadySignedException;
use Bigbank\XadesDocument\Exceptions\AppendException;
use Bigbank\XadesDocument\Exceptions\CannotSetSignatureIdException;
use Bigbank\XadesDocument\Exceptions\CertificateNotMatchedException;
use Bigbank\XadesDocument\Exceptions\DigitalDocumentCouldNotCreateFileException;
use Bigbank\XadesDocument\Exceptions\DigitalDocumentCouldNotOpenFileException;
use Bigbank\XadesDocument\Exceptions\DocumentEmptiedException;
use Bigbank\XadesDocument\Exceptions\FileMissingException;
use Bigbank\XadesDocument\Exceptions\InvalidMimetypeException;
use Bigbank\XadesDocument\Exceptions\NotFoundSignatureIdException;
use Bigbank\XadesDocument\Exceptions\SignatureEmptiedException;
use Bigbank\XadesDocument\Exceptions\SignatureLockedException;
use Bigbank\XadesDocument\Exceptions\UnknownCanonicalizationMethodException;
use Bigbank\XadesDocument\Exceptions\UnknownHashAlgorithmException;
use Bigbank\XadesDocument\Exceptions\XmlMissingException;
use DateTime;
use DOMDocument;
use DOMNode;
use SimpleXMLElement;
use ZipArchive;

abstract class DigitalDocument
{
    const SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    const RSA_SHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    const SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    const RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    const SHA384 = 'http://www.w3.org/2001/04/xmldsig-more#sha384';
    const RSA_SHA384 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384';
    const SHA512 = 'http://www.w3.org/2001/04/xmlenc#sha512';
    const RSA_SHA512 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512';
    const RIPEMD160 = 'http://www.w3.org/2001/04/xmlenc#ripemd160';
    const RSA_RIPEMD160 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-ripemd160';

    const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    const C14N_COMMENTS = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments';
    const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    const EXC_C14N_COMMENTS = 'http://www.w3.org/2001/10/xml-exc-c14n#WithComments';

    const TRANSFORM_XPATH = 'http://www.w3.org/TR/1999/REC-xpath-19991116';
    const XMLDSIGNS = 'http://www.w3.org/2000/09/xmldsig#';
    const XMLNS_ETSI = 'http://uri.etsi.org/01903/v1.3.2#';
    const XMLNS_SCHEMA_INSTANCE = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * @var string
     */
    protected $signatureAlgorithm = self::RSA_SHA1;
    /**
     * @var string
     */
    protected $signatureCanonicalizationMethod = self::C14N;
    /**
     * @var string
     */
    protected $signatureReferenceAlgorithm = self::SHA256;
    /**
     * @var string
     */
    protected $signatureReferenceCanonicalizationMethod = self::C14N;
    /**
     * @var string
     */
    protected $signatureCertAlgorithm = self::SHA256;

    /**
     * @var array
     */
    protected $signatures = array();

    /**
     * @var string
     */
    protected $signatureFolder = "META-INF/";

    /**
     * @var string
     */
    protected $signatureFilenamePattern = "/signatures(\\d+).xml/";

    /**
     * @var array
     */
    protected $documentMimeTypes = ['application/octet-stream'];

    /**
     * @var array
     */
    protected $documentFiles = array();

    protected $fileMimetypePatterns = array(
        'application/octet-stream' => "/.\\w$/"
    );

    /**
     * @var string|null
     */
    private $loadedFile;

    private $isFileTemp = false;

    /**
     * DigitalDocument constructor.
     * @param string|null $documentFileOrContent
     */
    public function __construct($documentFileOrContent = null)
    {
        if ($documentFileOrContent != null){
            $file = $documentFileOrContent;
            if(strlen($documentFileOrContent) > 4){
                $zipHeader = "\x50\x4B\x03\x04";
                if(strpos($documentFileOrContent, $zipHeader) === 0){
                    $file = @tempnam(sys_get_temp_dir(), 'digital_document_');
                    file_put_contents($file, $documentFileOrContent);
                    $this->isFileTemp = true;
                }
            }
            $this->load($file);
        }
    }

    /**
     * @param string $file
     * @throws FileMissingException
     * @throws InvalidMimetypeException
     * @throws DigitalDocumentCouldNotOpenFileException
     */
    protected function load($file)
    {
        $zipArchive = new ZipArchive();
        if($zipArchive->open($file) !== true){
            throw new DigitalDocumentCouldNotOpenFileException("Could not open file: {$file}");
        }
        $this->loadedFile = $file;
        $mimetype = $zipArchive->getFromName('mimetype');
        if (trim($mimetype) != $this->getMimeType())
            throw new InvalidMimetypeException("Invalid mimetype: {$mimetype}");
        $manifestContent = $zipArchive->getFromName('META-INF/manifest.xml');
        if ($manifestContent === false)
            throw new FileMissingException("Missing file: META-INF/manifest.xml");

        $manifest = $this->parseManifest($manifestContent);

        $documentPaths = $this->getDocumentPaths($manifest);
        $this->documentFiles = array();
        $loadedPaths = [
            'mimetype',
            'META-INF/manifest.xml'
        ];
        foreach ($documentPaths as $documentPath) {
            $documentContent = $zipArchive->getFromName($documentPath);
            if ($documentContent === false)
                throw new FileMissingException("Missing file: {$documentPath}");
            $this->documentFiles[] = array(
                'path' => $documentPath,
                'content' => $documentContent
            );
            $loadedPaths[] = $documentPath;
        }
        $unknownFiles = [];
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $file = $zipArchive->getNameIndex($i);
            if ($this->isSignatureFile($file)) {
                $signatureXmlData = $zipArchive->getFromName($file);
                $parsedSignature = $this->parseSignatureXmlData($signatureXmlData);
                $parsedSignature['locked'] = !empty($parsedSignature['signatureData']);
                $parsedSignature['path'] = $file;
                if ($parsedSignature['locked']) {
                    $parsedSignature['content'] = $signatureXmlData;
                }
                $this->signatures[] = $parsedSignature;
                $loadedPaths[] = $file;
                continue;
            }
            if (!in_array($file, $loadedPaths)) {
                $unknownFiles[$file] = $zipArchive->getFromName($file);
            }
        }
        $zipArchive->close();
        $this->loading($unknownFiles);
    }

    /**
     * @param array $files
     * @return void
     */
    protected abstract function loading($files);

    /**
     * @param string $data
     * @return array
     * @throws XmlMissingException
     */
    protected function parseSignatureXmlData($data)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($data);
        $singaturesXml = simplexml_load_string($data);
        $singaturesXml->registerXPathNamespace('x', self::XMLDSIGNS);
        $singatures = $singaturesXml->xpath('x:Signature');
        $signatureData = array();
        foreach ($singatures as $singature) {
            $signatureData['xmlId'] = (string)$singature['Id'];
            $singature->registerXPathNamespace('x', self::XMLDSIGNS);
            $canonicalizationMethod = $singature->xpath('//x:CanonicalizationMethod');
            if (count($canonicalizationMethod) == 0)
                throw new XmlMissingException('Singature: CanonicalizationMethod is missing.');
            $signatureData['canonicalizationMethod'] = $this->parseCanonicalizationMethod((string)$canonicalizationMethod[0]['Algorithm']);

            $signatureMethod = $singature->xpath('//x:SignatureMethod');
            if (count($signatureMethod) == 0)
                throw new XmlMissingException('Singature: SignatureMethod is missing.');
            $signatureData['algorithm'] = $this->parseHashAlgorithm((string)$signatureMethod[0]['Algorithm']);
            $transforms = $singature->xpath('//x:Reference//x:Transform');

            $selectedReferenceCanonicalizationAlgorithm = null;
            foreach ($transforms as $transform) {
                try {
                    $selectedReferenceCanonicalizationAlgorithm = $this->parseCanonicalizationMethod((string)$transform['Algorithm']);
                    break;
                } catch (UnknownCanonicalizationMethodException $ex) {
                }
            }

            if ($selectedReferenceCanonicalizationAlgorithm == null)
                throw new XmlMissingException("Singature reference canonicalization algorithm is missing.");
            $signatureData['referenceCanonicalizationMethod'] = $selectedReferenceCanonicalizationAlgorithm;

            $referenceDigestMethod = $singature->xpath('//x:Reference/x:DigestMethod');
            $signatureData['referenceAlgorithm'] = $this->parseHashAlgorithm((string)$referenceDigestMethod[0]['Algorithm']);

            $signatureValue = $singature->xpath('//x:SignatureValue');
            if (count($signatureValue) != 0 && !empty((string)$signatureValue[0]))
                $signatureData['signatureData'] = base64_decode((string)$signatureValue[0]);
            $x509Certificate = $singature->xpath('//x:X509Certificate');
            if (count($x509Certificate) == 0)
                throw new XmlMissingException('Singature: X509Certificate is missing.');

            $formatedCertificateData = $this->formatCertificate((string)$x509Certificate[0]);

            $signatureData['certificateData'] = $formatedCertificateData;
            $signatureData['x509Certificate'] = openssl_x509_parse($formatedCertificateData);

            $singature->registerXPathNamespace('y', self::XMLNS_ETSI);
            $signedSignatureProperties = $singature->xpath('//x:Object/y:QualifyingProperties/y:SignedProperties/y:SignedSignatureProperties');

            $signedSignatureProperties = $signedSignatureProperties[0];
            $signedSignatureProperties->registerXPathNamespace('y', self::XMLNS_ETSI);

            $signingTime = $signedSignatureProperties->xpath('//y:SigningTime');

            if (count($signingTime) == 0)
                throw new XmlMissingException('Singature: SigningTime is missing.');

            $signatureData['signedDatetime'] = (string)$signingTime[0];

            $cert = $signedSignatureProperties->xpath('//y:SigningCertificate/y:Cert');

            if (count($cert) == 0)
                throw new XmlMissingException("Cert is missing.");
            $cert = $cert[0];

            $cert->registerXPathNamespace('x', self::XMLDSIGNS);
            $cert->registerXPathNamespace('y', self::XMLNS_ETSI);
            $digestMethod = $cert->xpath('y:CertDigest/x:DigestMethod');
            if (count($digestMethod) == 0)
                throw new XmlMissingException("DigestMethod is missing");
            $signatureData['certAlgorithm'] = $this->parseHashAlgorithm((string)$digestMethod[0]['Algorithm']);
        }
        return $signatureData;
    }

    /**
     * @param array $manifest
     * @return array
     */
    private function getDocumentPaths($manifest)
    {
        $documentFiles = array();
        foreach ($manifest['fileEntries'] as $fileEntry) {
            if (in_array($fileEntry['media-type'], $this->documentMimeTypes))
                $documentFiles[] = $fileEntry['full-path'];
        }
        return $documentFiles;
    }

    /**
     * @param string $data
     * @return array
     */
    protected function parseManifest($data)
    {
        $manifest = array();
        $manifestXml = simplexml_load_string($data);
        $manifest['fileEntries'] = array();
        $fileEntries = $manifestXml->xpath('/manifest:manifest/manifest:file-entry');
        foreach ($fileEntries as $fileEntry) {
            $manifest['fileEntries'][] = array(
                'full-path' => $this->getAttributeValueFromXML('manifest:full-path', $fileEntry),
                'media-type' => $this->getAttributeValueFromXML('manifest:media-type', $fileEntry)
            );
        }
        return $manifest;
    }

    /**
     * @param string $name
     * @param SimpleXMLElement $simpleXMLElement
     * @return null|string
     */
    protected function getAttributeValueFromXML($name, $simpleXMLElement)
    {
        $result = $simpleXMLElement->xpath("@{$name}");
        if (count($result) == 0)
            return null;
        $result = $result[0];
        return (string)$result[preg_replace("/^\\w+:/", "", $name)];
    }

    /**
     * @param string $algorithm
     * @return string
     * @throws UnknownCanonicalizationMethodException
     */
    protected function parseCanonicalizationMethod($algorithm)
    {
        if (!in_array($algorithm, array(
            self::C14N,
            self::C14N_COMMENTS,
            self::EXC_C14N,
            self::EXC_C14N_COMMENTS
        ))
        ) {
            throw new UnknownCanonicalizationMethodException("Unknown canonicalization method: $algorithm");
        }
        return $algorithm;
    }

    /**
     * @param string $algorithm
     * @return string
     * @throws UnknownHashAlgorithmException
     */
    protected function parseHashAlgorithm($algorithm)
    {
        if (!in_array($algorithm, array(
            self::SHA1,
            self::SHA256,
            self::SHA384,
            self::SHA512,
            self::RIPEMD160,
            self::RSA_SHA1,
            self::RSA_SHA256,
            self::RSA_SHA384,
            self::RSA_SHA512,
            self::RSA_RIPEMD160
        ))
        ) {
            throw new UnknownHashAlgorithmException("Unknown algorithm: $algorithm");
        }
        return $algorithm;
    }

    /**
     * @param DOMNode $node
     * @param string $canonicalmethod
     * @param null|array $arXPath
     * @param null|array $prefixList
     * @return string
     */
    protected function canonicalizeData($node, $canonicalmethod, $arXPath = null, $prefixList = null)
    {
        $exclusive = false;
        $withComments = false;
        switch ($canonicalmethod) {
            case self::C14N:
                $exclusive = false;
                $withComments = false;
                break;
            case self::C14N_COMMENTS:
                $withComments = true;
                break;
            case self::EXC_C14N:
                $exclusive = true;
                break;
            case self::EXC_C14N_COMMENTS:
                $exclusive = true;
                $withComments = true;
                break;
        }

        if (is_null($arXPath) && ($node instanceof DOMNode) && ($node->ownerDocument !== null) && $node->isSameNode($node->ownerDocument->documentElement)) {
            $element = $node;
            while ($refnode = $element->previousSibling) {
                if ($refnode->nodeType == XML_PI_NODE || (($refnode->nodeType == XML_COMMENT_NODE) && $withComments))
                    break;
                $element = $refnode;
            }
            if ($refnode == null)
                $node = $node->ownerDocument;
        }

        return $node->C14N($exclusive, $withComments, $arXPath, $prefixList);
    }

    /**
     * @return string
     */
    private function generateXmlManifest()
    {
        $manifestEntries = array_merge(
            array(
                array(
                    'full-path' => '/',
                    'media-type' => $this->getMimeType()
                )
            ),
            $this->generateManifestEntryArray()
        );
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $mainElement = $dom->createElement('manifest:manifest');
        $mainElement->setAttribute('xmlns:manifest', 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0');
        foreach ($manifestEntries as $fileEntry) {
            $element = $dom->createElement('manifest:file-entry');
            foreach ($fileEntry as $key => $value) {
                $element->setAttribute('manifest:' . $key, $value);
            }
            $mainElement->appendChild($element);
        }
        $dom->appendChild($mainElement);
        return $dom->saveXML();
    }

    /**
     * @return array
     */
    protected function generateManifestEntryArray()
    {
        $manifestEntries = array();
        foreach ($this->documentFiles as $documentFile) {
            $manifestEntries[] = array(
                'full-path' => $documentFile['path'],
                'media-type' => $this->getMimetypeFromPath($documentFile['path']
                )
            );
        }
        return $manifestEntries;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function getMimetypeFromPath($path)
    {
        foreach ($this->fileMimetypePatterns as $mimetype => $regexPattern)
            if (preg_match($regexPattern, $path))
                return $mimetype;
        return '';
    }

    /**
     * @param string $path
     * @param string $content
     * @throws InvalidMimetypeException
     */
    public function addDocument($path, $content)
    {
        $documentMimeType = $this->getMimetypeFromPath($path);
        if (!in_array($documentMimeType, $this->documentMimeTypes))
            throw new InvalidMimetypeException("Invalid document's mime type: {$documentMimeType}");
        $pathList = array();
        foreach ($this->documentFiles as $documentFile) {
            $pathList[] = $documentFile['path'];
        }
        $this->documentFiles[] = array(
            'path' => $this->getDifferentPathFilename($path, $pathList),
            'content' => $content
        );
    }

    /**
     * @param integer $id
     * @return string
     */
    protected function getSignatureFilename($id)
    {
        return "signatures{$id}.xml";
    }

    /**
     * @param string $path
     * @return bool|int
     */
    protected function isSignatureFile($path)
    {
        if (strpos($path, $this->signatureFolder) !== 0)
            return false;
        return $this->isSignatureFilename(substr($path, strlen($this->signatureFolder), strlen($path)));
    }

    /**
     * @param string $filename
     * @return bool
     */
    protected function isSignatureFilename($filename)
    {
        return (bool)preg_match('/signatures([0-9]+).xml$/', $filename);
    }

    /**
     * @param integer $id
     * @return string
     */
    protected function getSignaturePath($id)
    {
        return $this->signatureFolder . $this->getSignatureFilename($id);
    }


    protected function checkConditionsForSaving()
    {
        if (empty($this->documentFiles))
            throw new DocumentEmptiedException("Document is emptied.");
    }

    /**
     * @param $file
     * @return string|null
     * @throws DigitalDocumentCouldNotCreateFileException
     */
    public function save($file = null)
    {
        $this->checkConditionsForSaving();
        $savingFile = $file;
        $isTemp = false;
        if($savingFile == null && $this->loadedFile == null){
            $savingFile = @tempnam(sys_get_temp_dir(), 'digital_document_');
            $isTemp = true;
        }
        elseif ($this->loadedFile != null){
            $savingFile = $this->loadedFile;
        }

        if (file_exists($savingFile))
            @unlink($savingFile);

        $zipArchive = new ZipArchive();
        if ($zipArchive->open($savingFile, ZipArchive::CREATE) !== true)
            throw new DigitalDocumentCouldNotCreateFileException("Could create file: {$savingFile}");

        $zipArchive->addFromString('mimetype', $this->getMimeType());
        $zipArchive->addFromString('META-INF/manifest.xml', $this->generateXmlManifest());
        foreach ($this->documentFiles as $documentFile) {
            $zipArchive->addFromString($documentFile['path'], $documentFile['content']);
        }

        $extraFiles = $this->extraFiles();
        foreach ($extraFiles as $path => $data) {
            $zipArchive->addFromString($path, $data);
        }
        $xmlSignatures = $this->generateXmlSignatures();
        foreach ($xmlSignatures as $path => $xmlSignature) {
            $zipArchive->addFromString($path, $xmlSignature);
        }
        $zipArchive->close();
        if($isTemp || $this->isFileTemp){
            $data = file_get_contents($savingFile);
            @unlink($savingFile);
            return $data;
        }
        return null;
    }

    /**
     * @return array
     */
    protected function extraFiles()
    {
        return array();
    }

    /**
     * @return array
     */
    private function generateXmlSignatures()
    {
        $data = array();
        foreach ($this->signatures as $index => $signature) {
            if ($signature['locked'] == true) {
                $data[$signature['path']] = $signature['content'];
                continue;
            }
            $dom = $this->generateXmlSignature($index, $signature);
            $data[$this->getSignaturePath($signature['id'])] = $dom->saveXML();
        }
        return $data;
    }

    /**
     * @param integer $index
     * @param array $signature
     * @return DOMDocument
     */
    protected abstract function generateXmlSignature($index, $signature);

    /**
     * @param string $certificateData
     * @param string|null $signedDatetime
     * @return integer
     */
    public function addPrepareSignature($certificateData, $signedDatetime = null)
    {
        $formatedCertificateData = $this->formatCertificate($certificateData);
        $this->signatures[] = array(
            'locked' => false,
            'signatureData' => null,
            'certificateData' => $formatedCertificateData,
            'x509Certificate' => openssl_x509_parse($formatedCertificateData),
            'signedDatetime' => $signedDatetime == null ? (new DateTime())->format(DATE_W3C) : $signedDatetime,
            'canonicalizationMethod' => $this->signatureCanonicalizationMethod,
            'algorithm' => $this->signatureAlgorithm,
            'referenceCanonicalizationMethod' => $this->signatureReferenceCanonicalizationMethod,
            'referenceAlgorithm' => $this->signatureReferenceAlgorithm,
            'certAlgorithm' => $this->signatureCertAlgorithm,
        );
        $this->generateSignaturesId();
        return $this->signatures[count($this->signatures) - 1]['id'];
    }

    /**
     * @param DOMDocument $dom
     * @param array $signature
     * @return DOMNode
     */
    protected function createSignatureValue($dom, $signature)
    {
        $data = empty($signature['signatureData']) ? '' : $this->formatSignatureDataForXml($signature['signatureData']);
        return $dom->createElement('SignatureValue', $data);
    }

    /**
     * @param DOMDocument $dom
     * @param array $signature
     * @return DOMNode
     */
    protected function createKeyInfoXmlElement($dom, $signature)
    {
        $keyInfo = $dom->createElement('KeyInfo');
        $x509Data = $dom->createElement('X509Data');
        $x509Data->appendChild($dom->createElement('X509Certificate', $this->formatX509CertificateForXml($signature['certificateData'])));
        $keyInfo->appendChild($x509Data);
        return $keyInfo;
    }

    /**
     * @param DOMDocument $dom
     * @param array $signature
     * @return DOMNode
     */
    protected function createSignedInfoXmlElement($dom, $signature)
    {
        $signedInfo = $dom->createElement('SignedInfo');
        $signedInfo->setAttribute('xmlns', self::XMLDSIGNS);
        $canonicalizationMethod = $dom->createElement('CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', $signature['canonicalizationMethod']);
        $signedInfo->appendChild($canonicalizationMethod);
        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', $signature['algorithm']);
        $signedInfo->appendChild($signatureMethod);

        foreach ($this->documentFiles as $documentFile) {
            $digestValue = $this->generateHashValue($documentFile['content'], $signature['referenceAlgorithm']);
            $reference = $this->createSignatureReference($dom, $documentFile['path'], $digestValue, $signature['referenceAlgorithm']);
            $signedInfo->appendChild($reference);
        }
        return $signedInfo;
    }

    /**
     * @param DOMDocument $dom
     * @param array $signature
     * @return DOMNode
     */
    protected function createSignedSignaturePropertiesXmlElement($dom, $signature)
    {
        $signedSignatureProperties = $dom->createElement('SignedSignatureProperties');
        $signedSignatureProperties->appendChild($dom->createElement('SigningTime', $signature['signedDatetime']));

        $signingCertificate = $dom->createElement('SigningCertificate');
        $signedSignatureProperties->appendChild($signingCertificate);

        $cert = $dom->createElement('Cert');
        $signingCertificate->appendChild($cert);

        $certDigest = $dom->createElement('CertDigest');
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', $signature['certAlgorithm']);
        $digestMethod->setAttribute('xmlns', self::XMLDSIGNS);
        $certDigest->appendChild($digestMethod);

        $hashValue = $this->generateHashValue(base64_decode($this->formatX509CertificateForXml($signature['certificateData'])), $signature['certAlgorithm']);
        $digestValue = $dom->createElement('DigestValue', $hashValue);
        $digestValue->setAttribute('xmlns', self::XMLDSIGNS);
        $certDigest->appendChild($digestValue);
        $cert->appendChild($certDigest);

        $reversedArrayIssuer = array_reverse($signature['x509Certificate']['issuer']);
        $issuer = "";
        foreach ($reversedArrayIssuer as $key => $value) {
            if ($issuer == "")
                $issuer = "{$key}={$value}";
            else
                $issuer .= ",{$key}={$value}";
        }

        $issuerSerial = $dom->createElement('IssuerSerial');
        $x509IssuerName = $dom->createElement('X509IssuerName', $issuer);
        $x509IssuerName->setAttribute('xmlns', self::XMLDSIGNS);
        $issuerSerial->appendChild($x509IssuerName);
        $x509SerialNumber = $dom->createElement('X509SerialNumber', $signature['x509Certificate']['serialNumber']);
        $x509SerialNumber->setAttribute('xmlns', self::XMLDSIGNS);
        $issuerSerial->appendChild($x509SerialNumber);
        $cert->appendChild($issuerSerial);
        return $signedSignatureProperties;
    }

    /**
     * @param string $certificateData
     * @return string
     */
    protected function formatX509CertificateForXml($certificateData)
    {
        return trim(preg_replace("/^-----BEGIN\\sCERTIFICATE-----|-----END\\sCERTIFICATE-----$/i", "", trim($certificateData)));
    }

    /**
     * @param string $data
     * @return string
     */
    protected function formatSignatureDataForXml($data)
    {
        $formatedSignatureData = "";
        $signatureValueData = base64_encode($data);
        while ($signatureValueData != '') {
            $appendData = '';
            if (strlen($signatureValueData) > 76) {
                $appendData = substr($signatureValueData, 0, 76);
                $signatureValueData = substr($signatureValueData, 76);
            } else {
                $appendData = $signatureValueData;
                $signatureValueData = '';
            }
            $formatedSignatureData .= "\n$appendData";
        }
        return $formatedSignatureData;
    }

    /**
     * Generate signature id for different number
     */
    private function generateSignaturesId()
    {
        $alreadyIdList = [];
        $missingIdSignatureIndexList = [];
        for ($signatureIndex = 0; $signatureIndex < count($this->signatures); $signatureIndex++) {
            if (isset($this->signatures[$signatureIndex]['id']))
                $alreadyIdList[] = $this->signatures[$signatureIndex]['id'];
            else
                $missingIdSignatureIndexList[] = $signatureIndex;
        }
        $number = 1;
        foreach ($missingIdSignatureIndexList as $missingIdSignatureIndex) {
            while ($number > 0) {
                if (!in_array($number, $alreadyIdList))
                    break;
                $number++;
            }
            $this->signatures[$missingIdSignatureIndex]['id'] = $number;
        }
    }

    /**
     * @param string $uri
     * @return string
     */
    protected function encodeUri($uri)
    {
        $encoded = rawurlencode($uri);
        $encoded = str_replace('%2F', '/', $encoded);
        $encoded = str_replace('%23', '#', $encoded);
        $encoded = str_replace('%28', '(', $encoded);
        $encoded = str_replace('%29', ')', $encoded);
        $encoded = str_replace('%26', '&', $encoded);
        $encoded = str_replace('%3F', '?', $encoded);
        $encoded = str_replace('%40', '@', $encoded);
        return $encoded;
    }

    /**
     * @param DOMDocument $dom
     * @param string $uri
     * @param string $digestValue
     * @param string $digestMethodAlgorithm
     * @param array $transforms
     * @param string|null $type
     * @return DOMNode
     */
    protected function createSignatureReference($dom, $uri, $digestValue, $digestMethodAlgorithm, $transforms = [], $type = null)
    {
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('xmlns', self::XMLDSIGNS);
        $reference->setAttribute('URI', $this->encodeUri($uri));
        if ($type != null)
            $reference->setAttribute('Type', $type);
        if (!empty($transforms)) {
            $transformsElement = $dom->createElement('Transforms');
            $transformsElement->setAttribute('xmlns', self::XMLDSIGNS);
            $reference->appendChild($transformsElement);
            foreach ($transforms as $key => $value) {
                $transformElement = $dom->createElement('Transform');
                if (is_numeric($key))
                    $transformElement->setAttribute('Algorithm', $value);
                else
                    $transformElement->setAttribute('Algorithm', $key);
                $transformsElement->appendChild($transformElement);
                if ($key === self::TRANSFORM_XPATH) {
                    $xpathElement = $dom->createElement('XPath', $value);
                    $transformElement->appendChild($xpathElement);
                }
            }
        }
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', $digestMethodAlgorithm);
        $reference->appendChild($digestMethod);
        $reference->appendChild($dom->createElement('DigestValue', $digestValue));
        return $reference;
    }

    /**
     * @param string $content
     * @param string $algorithm
     * @param bool $base64
     * @param bool $hashOutputRaw
     * @return string
     */
    protected function generateHashValue($content, $algorithm, $base64 = true, $hashOutputRaw = true)
    {
        $hashAlgorithm = null;
        switch ($algorithm) {
            case self::SHA1:
            case self::RSA_SHA1:
                $hashAlgorithm = 'sha1';
                break;
            case self::SHA256:
            case self::RSA_SHA256:
                $hashAlgorithm = 'sha256';
                break;
            case self::SHA384:
            case self::RSA_SHA384:
                $hashAlgorithm = 'sha384';
                break;
            case self::SHA512:
            case self::RSA_SHA512:
                $hashAlgorithm = 'sha512';
                break;
            case self::RIPEMD160:
            case self::RSA_RIPEMD160:
                $hashAlgorithm = 'ripemd160';
        }
        $hashData = hash($hashAlgorithm, $content, $hashOutputRaw);
        if ($base64)
            return base64_encode($hashData);
        return $hashData;
    }

    /**
     * @param string $certificate
     * @return string
     */
    protected function formatCertificate($certificate)
    {
        $certificateTemp = trim(preg_replace("/^-----BEGIN\\sCERTIFICATE-----|-----END\\sCERTIFICATE-----$|\\n/i", "", trim($certificate)));
        $formatedCertificateTemp = '';
        while ($certificateTemp != '') {
            $appendData = '';
            if (strlen($certificateTemp) > 64) {
                $appendData = substr($certificateTemp, 0, 64);
                $certificateTemp = substr($certificateTemp, 64);
            } else {
                $appendData = $certificateTemp;
                $certificateTemp = '';
            }
            $formatedCertificateTemp .= "\n$appendData";
        }
        $formatedCertificateTemp .= "\n";
        return "-----BEGIN CERTIFICATE-----{$formatedCertificateTemp}-----END CERTIFICATE-----";
    }

    /**
     * @return string
     */
    public abstract function getMimeType();

    /**
     * @param string $signedData
     * @param string $certificate
     * @throws CertificateNotMatchedException
     */
    public function setSignatureData($signedData, $certificate)
    {
        $formatedCertificate = $this->formatCertificate($certificate);
        for ($i = 0; $i < count($this->signatures); $i++) {
            if ($this->signatures[$i]['certificateData'] == $formatedCertificate) {
                $this->setSignatureDataByIndex($signedData, $i);
                return;
            }
        }
        throw new CertificateNotMatchedException('Certificate is not matched.');
    }

    /**
     * @param string $signedData
     * @param integer $id
     */
    public function setSignatureDataById($signedData, $id)
    {
        $signatureIndex = $this->getSignatureIndexById($id);
        $this->setSignatureDataByIndex($signedData, $signatureIndex);
    }

    /**
     * @param $signedData
     * @param $index
     * @throws SignatureLockedException
     */
    protected function setSignatureDataByIndex($signedData, $index)
    {
        if($this->signatures[$index]['locked']){
            throw new SignatureLockedException("Signature [id = {$this->signatures[$index]['id']}] is locked.");
        }
        $this->signatures[$index]['signatureData'] = $signedData;
    }

    /**
     * @param string $signedData
     * @throws SignatureEmptiedException
     */
    public function setLastSignatureData($signedData)
    {
        if (empty($this->signatures))
            throw new SignatureEmptiedException();
        $this->setSignatureDataByIndex($signedData, count($this->signatures) - 1);
    }

    /**
     * @param integer $index
     * @param bool $rawOutput
     * @return string
     * @throws AlreadySignedException
     */
    protected function getRequestSignatureHashByIndex($index, $rawOutput = false)
    {
        if ($this->signatures[$index]['locked'] == true)
            throw new AlreadySignedException('Cannot create request signature hash');
        $this->checkConditionsForSaving();
        $this->extraFiles();
        $signatureDom = $this->generateXmlSignature($index, $this->signatures[$index]);
        $domTemp = new DOMDocument('1.0', 'utf-8');
        $domTemp->loadXML($signatureDom->saveXML());
        return $this->generateHashValue($this->canonicalizeData($domTemp->getElementsByTagName('SignedInfo')->item(0), $this->signatures[$index]['canonicalizationMethod']), $this->signatures[$index]['algorithm'], false, $rawOutput);
    }

    /**
     * @param integer $id
     * @param bool $rawOutput
     * @return string
     */
    public function getRequestSignatureHash($id, $rawOutput = false)
    {
        $signatureIndex = $this->getSignatureIndexById($id);
        return $this->getRequestSignatureHashByIndex($signatureIndex, $rawOutput);
    }

    /**
     * @param bool $rawOutput
     * @return string
     * @throws SignatureEmptiedException
     */
    public function getLastSignatureRequestHash($rawOutput = false)
    {
        if (empty($this->signatures))
            throw new SignatureEmptiedException();
        return $this->getRequestSignatureHashByIndex(count($this->signatures) - 1, $rawOutput);
    }

    /**
     * @param string $algorithm
     */
    public function setSignatureReferenceAlgorithm($algorithm)
    {
        $this->signatureReferenceAlgorithm = $this->parseHashAlgorithm($algorithm);
    }

    /**
     * @param string $algorithm
     */
    public function setSignatureCertAlgorithm($algorithm)
    {
        $this->signatureCertAlgorithm = $this->parseHashAlgorithm($algorithm);
    }

    /**
     * @param $canonicalizationMethod
     */
    public function setSignatureReferenceCanonicalizationMethod($canonicalizationMethod)
    {
        $this->signatureReferenceCanonicalizationMethod = $this->parseCanonicalizationMethod($canonicalizationMethod);
    }

    /**
     * @param $algorithm
     */
    public function setSignatureAlgorithm($algorithm)
    {
        $this->signatureAlgorithm = $this->parseHashAlgorithm($algorithm);
    }

    /**
     * @param integer $id
     * @param integer $index
     * @throws CannotSetSignatureIdException
     * @throws SignatureLockedException
     */
    protected function setSignatureIdByIndex($id, $index)
    {
        foreach ($this->signatures as $key => $signature) {
            if ($index != $key && $signature['id'] == $id) {
                throw new CannotSetSignatureIdException();
            }
        }
        if($this->signatures[$index]['locked']){
            throw new SignatureLockedException("Signature [id = {$this->signatures[$index]['id']}] is locked.");
        }
        $this->signatures[$index]['id'] = $id;
    }

    /**
     * @param integer $id
     * @throws SignatureEmptiedException
     */
    public function setLastSignatureId($id)
    {
        if (empty($this->signatures))
            throw new SignatureEmptiedException();
        $this->setSignatureIdByIndex($id, count($this->signatures) - 1);
    }


    /**
     * @param integer $oldId
     * @param integer $newId
     */
    public function setSignatureId($oldId, $newId)
    {
        $signatureIndex = $this->getSignatureIndexById($oldId);
        $this->setSignatureIdByIndex($newId, $signatureIndex);
    }

    /**
     * @param integer $id
     * @return int
     * @throws NotFoundSignatureIdException
     * @throws SignatureEmptiedException
     */
    protected function getSignatureIndexById($id)
    {
        if (empty($this->signatures))
            throw new SignatureEmptiedException();
        foreach ($this->signatures as $index => $signature) {
            if ($signature['id'] == $id) {
                return $index;
            }
        }
        throw new NotFoundSignatureIdException("Id [{$id}] is not found");
    }

    /**
     * @param $canonicalizationMethod
     */
    public function setSignatureCanonicalizationMethod($canonicalizationMethod)
    {
        $this->signatureCanonicalizationMethod = $this->parseCanonicalizationMethod($canonicalizationMethod);
    }

    /**
     * @return bool
     */
    public function isSignedForComplete()
    {
        if (empty($this->signatures))
            return false;
        foreach ($this->signatures as $signature) {
            if (empty($signature['signatureData']))
                return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function isSigned()
    {
        foreach ($this->signatures as $signature) {
            if (!empty($signature['signatureData']))
                return true;
        }
        return false;
    }

    /**
     * @param string $fileOrContent
     */
    public function appendFileOrContent($fileOrContent)
    {
        $class = get_called_class();
        $loadedDocument = new $class($fileOrContent);
        $this->appendDigitalDocument($loadedDocument);
    }

    /**
     * @param string $path
     * @param array [string] $pathList
     * @return string
     */
    protected function getDifferentPathFilename($path, $pathList)
    {
        $fileOriginalPathInfo = pathinfo($path);
        $foundSameFilenameCount = 0;
        $fixedPath = $path;
        do {
            $againDo = false;
            if ($foundSameFilenameCount > 0) {
                $fixedPath = (($fileOriginalPathInfo['dirname'] == '.' ? '' : "{$fileOriginalPathInfo['dirname']}/") . "{$fileOriginalPathInfo['filename']}-{$foundSameFilenameCount}.{$fileOriginalPathInfo['extension']}");
            }
            foreach ($pathList as $file) {
                if (strtoupper($file) == strtoupper($fixedPath)) {
                    $foundSameFilenameCount++;
                    $againDo = true;
                }
            }

        } while ($againDo);
        return $fixedPath;
    }

    /**
     * @param DigitalDocument $secondDigitalDocument
     * @throws AppendException
     */
    public function appendDigitalDocument(self $secondDigitalDocument)
    {
        $currentDocumentsHash = array();
        foreach ($this->documentFiles as $documentFile) {
            $currentDocumentsHash[] = md5($documentFile['content']);
        }

        $differentDocuments = array();
        $sameDocumentCount = 0;
        foreach ($secondDigitalDocument->documentFiles as $documentFile) {
            if (in_array(md5($documentFile['content']), $currentDocumentsHash))
                $sameDocumentCount++;
            else {
                $differentDocuments[] = array(
                    'path' => $documentFile['path'],
                    'content' => $documentFile['content']
                );
            }
        }

        if ($this->isSigned() && count($differentDocuments) != 0) {
            throw new AppendException("Documents could not append, because file was signed.");
        }

        $differentSignatures = array();

        foreach ($secondDigitalDocument->signatures as $signature) {
            $foundSameSignature = false;
            foreach ($this->signatures as $currentSignature) {
                if ($currentSignature['id'] == $signature['id']) {
                    $foundSameSignature = true;
                    if (md5($currentSignature['signatureData']) != md5($signature['signatureData']) || md5($currentSignature['certificateData']) != md5($signature['certificateData']) || md5($currentSignature['signedDatetime']) != md5($signature['signedDatetime']))
                        throw new AppendException("Cannot append signature, because signature id [{$currentSignature['id']}] is same. Must be diffrerent.");
                }
            }
            if (!$foundSameSignature)
                $differentSignatures[] = $signature;
        }

        $this->appending($secondDigitalDocument);
        foreach ($differentDocuments as $differentDocument) {
            $this->addDocument($differentDocument['path'], $differentDocument['content']);
        }

        foreach ($differentSignatures as $signature) {
            $this->signatures[] = $signature;
        }

    }

    protected abstract function appending($secondDigitalDocument);

    /**
     * @return array
     */
    public function getSigners()
    {
        $signers = array();
        foreach ($this->signatures as $signature) {
            if (!empty($signature['signatureData'])) {
                $signers[] = array(
                    'id' => $signature['id'],
                    'signedDatetime' => $signature['signedDatetime'],
                    'certificate' => $signature['certificateData']
                );
            }
        }
        return $signers;
    }

    public function __destruct()
    {
        if($this->isFileTemp){
            @unlink($this->loadedFile);
        }
    }

    /**
     * @return array
     * @throws SignatureEmptiedException
     */
    protected function getLastSignature()
    {
        if (empty($this->signatures))
            throw new SignatureEmptiedException();
        return $this->signatures[count($this->signatures) -1];
    }

    /**
     * @return string
     */
    public function getLastSignatureAlgorithm()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['algorithm'];
    }

    /**
     * @return string
     */
    public function getLastSignatureCanonicalizationMethod()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['canonicalizationMethod'];
    }

    /**
     * @return string
     */
    public function getLastSignatureReferenceCanonicalizationMethod()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['referenceCanonicalizationMethod'];
    }

    /**
     * @return string
     */
    public function getLastSignatureReferenceAlgorithm()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['referenceAlgorithm'];
    }

    /**
     * @return string
     */
    public function getLastSignatureCertAlgorithm()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['certAlgorithm'];
    }

    /**
     * @return string
     */
    public function getLastSignatureCertificate()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['certificateData'];
    }

    /**
     * @return bool
     */
    public function isLastSignatureLocked()
    {
        $lastSignature = $this->getLastSignature();
        return $lastSignature['locked'];
    }

    /**
     * @param integer $id
     * @return array
     */
    protected function getSignatureById($id)
    {
        return $this->signatures[$this->getSignatureIndexById($id)];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureAlgorithmById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['algorithm'];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureCanonicalizationMethodById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['canonicalizationMethod'];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureReferenceCanonicalizationMethodById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['referenceCanonicalizationMethod'];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureReferenceAlgorithmById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['referenceAlgorithm'];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureCertAlgorithmById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['certAlgorithm'];
    }

    /**
     * @param integer $id
     * @return string
     */
    public function getSignatureCertificateById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['certificateData'];
    }

    /**
     * @param integer $id
     * @return bool
     */
    public function isSignatureLockedById($id)
    {
        $lastSignature = $this->getSignatureById($id);
        return $lastSignature['locked'];
    }

}