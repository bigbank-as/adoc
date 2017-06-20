<?php

namespace Bigbank\XadesDocument;

use Bigbank\XadesDocument\Exceptions\ADoc\ADocAuthorEmptiedException;
use Bigbank\XadesDocument\Exceptions\ADoc\ADocFieldMissingException;
use Bigbank\XadesDocument\Exceptions\AlreadySignedException;
use Bigbank\XadesDocument\Exceptions\AppendException;
use Bigbank\XadesDocument\Exceptions\DigitalDocumentException;
use Bigbank\XadesDocument\Exceptions\FileMissingException;
use Bigbank\XadesDocument\Exceptions\XmlMissingException;
use DOMDocument;

class ADoc extends DigitalDocument
{
    const STANDARD_VERSION = 'ADOC-V1.0';

    const TYPE_UNSIGNABLE_METADATA = 'http://www.archyvai.lt/adoc/2008/relationships/metadata/unsignable';
    const TYPE_SIGNABLE_METADATA = 'http://www.archyvai.lt/adoc/2008/relationships/metadata/signable';
    const TYPE_CONTENT_MAIN = 'http://www.archyvai.lt/adoc/2008/relationships/content/main';
    const TYPE_CONTENT_APPENDIX = 'http://www.archyvai.lt/adoc/2008/relationships/content/appendix';
    const TYPE_SIGNATURES = 'http://www.archyvai.lt/adoc/2008/relationships/signatures';
    const XMLNS_RELATIONSHIPS = 'http://www.archyvai.lt/adoc/2008/relationships';

    const MIMETYPE = "application/vnd.lt.archyvai.adoc-2008";

    protected $documentMimeTypes = ['application/pdf'];

    protected $fileMimetypePatterns = array(
        'application/pdf' => "/.pdf$/"
    );

    protected $signatureFolder = "META-INF/signatures/";

    const APPENDICES_FOLDER = "appendices";

    const METADATA_FOLDER = 'metadata/';


    protected $unsignableMetadataPath = self::METADATA_FOLDER . "unsignableMetadata0.xml";
    protected $signableMetadataContents = array();
    protected $relationsPath = 'META-INF/relations.xml';

    protected $lockedSignableMetadataFiles = array();

    /**
     * @var string
     */
    protected $documentTitle = "Document Title";

    /**
     * @var array
     */
    protected $authors = array();

    /**
     * @var string
     */
    private $documentCategory = "GGeDOC";

    /**
     * @inheritdoc
     */
    protected function generateXmlSignature($index, $signature)
    {
        $signatureDom = new DOMDocument('1.0', 'utf-8');
        $signatureDom->formatOutput = true;
        $mainElement = $signatureDom->createElement('document-signatures');
        $mainElement->setAttribute('xmlns', 'urn:oasis:names:tc:opendocument:xmlns:digitalsignature:1.0');
        $signatureDom->appendChild($mainElement);

        $signatureElement = $signatureDom->createElement('Signature');
        $signatureElement->setAttribute('xmlns', self::XMLDSIGNS);
        $signatureElement->setAttribute('xmlns:xsi', self::XMLNS_SCHEMA_INSTANCE);
        $signatureElement->setAttribute('Id', "SignatureElem_{$signature['id']}");
        $mainElement->appendChild($signatureElement);

        $signedInfo = $this->createSignedInfoXmlElement($signatureDom, $signature);
        $signatureElement->appendChild($signedInfo);

        $domMainSignableMetadata = new DOMDocument('1.0', 'utf-8');

        $domMainSignableMetadata->loadXML($this->signableMetadataContents[self::METADATA_FOLDER . 'signableMetadata0.xml']['content']);

        for ($i = 0; $i < count($this->authors); $i++) {
            $digestValue = $this->generateHashValue($this->canonicalizeData($domMainSignableMetadata->getElementsByTagName('author')->item($i), $signature['referenceCanonicalizationMethod']), $signature['referenceAlgorithm']);
            $reference = $this->createSignatureReference($signatureDom, self::METADATA_FOLDER . 'signableMetadata0.xml', $digestValue, $signature['referenceAlgorithm'], [self::TRANSFORM_XPATH => "ancestor-or-self::*[@ID='author_{$i}']", $signature['referenceCanonicalizationMethod']]);
            $signedInfo->appendChild($reference);
        }

        $digestValue = $this->generateHashValue($this->canonicalizeData($domMainSignableMetadata->getElementsByTagName('document')->item(0), $signature['referenceCanonicalizationMethod']), $signature['referenceAlgorithm']);
        $reference = $this->createSignatureReference($signatureDom, self::METADATA_FOLDER . 'signableMetadata0.xml', $digestValue, $signature['referenceAlgorithm'], [self::TRANSFORM_XPATH => "ancestor-or-self::*[@ID='document_0']", $signature['referenceCanonicalizationMethod']]);
        $signedInfo->appendChild($reference);

        $domSignableMetadata = new DOMDocument('1.0', 'utf-8');
        $domSignableMetadata->loadXML($this->signableMetadataContents[self::METADATA_FOLDER . "signableMetadata{$signature['id']}.xml"]['content']);
        $digestValue = $this->generateHashValue($this->canonicalizeData($domSignableMetadata->getElementsByTagName('signature')->item(0), $signature['referenceCanonicalizationMethod']), $signature['referenceAlgorithm']);
        $reference = $this->createSignatureReference($signatureDom, "metadata/signableMetadata{$signature['id']}.xml", $digestValue, $signature['referenceAlgorithm'], [self::TRANSFORM_XPATH => "ancestor-or-self::*[@ID='signature_{$signature['id']}']", $signature['referenceCanonicalizationMethod']]);
        $signedInfo->appendChild($reference);

        $signatureElement->appendChild($this->createSignatureValue($signatureDom, $signature));

        $signatureElement->appendChild($this->createKeyInfoXmlElement($signatureDom, $signature));

        $object = $signatureDom->createElement('Object');
        $signatureElement->appendChild($object);

        $qualifyingProperties = $signatureDom->createElement('QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', "#SignatureElem_{$signature['id']}");
        $qualifyingProperties->setAttribute('xmlns', self::XMLNS_ETSI);
        $object->appendChild($qualifyingProperties);

        $signedProperties = $signatureDom->createElement('SignedProperties');
        $signedProperties->setAttribute('Id', "SignedPropertiesElem_{$signature['id']}");

        $signedSignatureProperties = $this->createSignedSignaturePropertiesXmlElement($signatureDom, $signature);
        $signedProperties->appendChild($signedSignatureProperties);

        $signaturePolicyIdentifier = $signatureDom->createElement('SignaturePolicyIdentifier');
        $signaturePolicyIdentifier->appendChild($signatureDom->createElement('SignaturePolicyImplied'));
        $signedSignatureProperties->appendChild($signaturePolicyIdentifier);

        $qualifyingProperties->appendChild($signedProperties);

        $domTemp = new DOMDocument('1.0', 'utf-8');
        $domTemp->loadXML($signatureDom->saveXML());

        $digestValue = $this->generateHashValue($this->canonicalizeData($domTemp->getElementsByTagName('SignedProperties')->item(0), $signature['referenceCanonicalizationMethod']), $signature['referenceAlgorithm']);
        $reference = $this->createSignatureReference($signatureDom, "#SignedPropertiesElem_{$signature['id']}", $digestValue, $signature['referenceAlgorithm'], [$signature['referenceCanonicalizationMethod']], self::XMLNS_ETSI . 'SignedProperties');
        $signedInfo->appendChild($reference);

        return $signatureDom;
    }

    /**
     * @inheritdoc
     */
    public function addDocument($path, $content)
    {
        $pathinfo = pathinfo($path);
        $filename = $pathinfo['basename'];
        if (count($this->documentFiles) > 0) {
            $filename = self::APPENDICES_FOLDER . '/' . $pathinfo['basename'];
        }
        parent::addDocument($filename, $content);
    }

    /**
     * @inheritdoc
     */
    protected function generateManifestEntryArray()
    {
        $data = parent::generateManifestEntryArray();
        $data[] = array('full-path' => 'META-INF/', 'media-type' => "");
        $data[] = array('full-path' => self::METADATA_FOLDER, 'media-type' => $this->getMimeType() . '#metadata-folder');
        $data[] = array('full-path' => $this->unsignableMetadataPath, 'media-type' => 'text/xml');

        if (!array_key_exists(self::METADATA_FOLDER . 'signableMetadata0.xml', $this->lockedSignableMetadataFiles))
            $data[] = array('full-path' => self::METADATA_FOLDER . 'signableMetadata0.xml', 'media-type' => 'text/xml');

        foreach ($this->lockedSignableMetadataFiles as $path => $content) {
            $data[] = array('full-path' => $path, 'media-type' => 'text/xml');
        }

        $data[] = array('full-path' => $this->relationsPath, 'media-type' => 'text/xml');
        if (count($this->documentFiles) > 1)
            $data[] = array('full-path' => self::APPENDICES_FOLDER . '/', 'media-type' => "");
        if (count($this->signatures) != 0) {
            $data[] = array('full-path' => $this->signatureFolder, 'media-type' => $this->getMimeType() . '#signatures-folder');
            foreach ($this->signatures as $signature) {
                if ($signature['locked'] == true) {
                    $data[] = array('full-path' => $signature['path'], 'media-type' => 'text/xml');
                } else {
                    $data[] = array('full-path' => $this->getSignaturePath($signature['id']), 'media-type' => 'text/xml');
                    $data[] = array('full-path' => self::METADATA_FOLDER . "signableMetadata{$signature['id']}.xml", 'media-type' => 'text/xml');
                }
            }
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function extraFiles()
    {
        $files = array();
        $files[$this->unsignableMetadataPath] = $this->generateXmlUnsignableMetadata();
        $this->signableMetadataContents = $this->generateXmlSignableMetadata();
        foreach ($this->signableMetadataContents as $path => $signableMetadata) {
            $files[$path] = $signableMetadata['content'];
        }
        $files[$this->relationsPath] = $this->generateXmlRelations();
        return $files;
    }

    /**
     * @inheritdoc
     */
    protected function checkConditionsForSaving()
    {
        parent::checkConditionsForSaving();

        if (empty($this->authors))
            throw new ADocAuthorEmptiedException('Author is emptied.');
    }

    /**
     * @return array
     */
    private function generateXmlSignableMetadata()
    {
        $xmlSignableMetaData = [];
        $signableMetadataPath = self::METADATA_FOLDER . 'signableMetadata0.xml';
        if (array_key_exists($signableMetadataPath, $this->lockedSignableMetadataFiles)) {
            $xmlSignableMetaData[$signableMetadataPath] = array(
                'locked' => true,
                'content' => $this->lockedSignableMetadataFiles[$signableMetadataPath]
            );
        } else {
            $dom = new DOMDocument('1.0', 'utf-8');
            $mainElement = $dom->createElement('sig:metadata');
            $mainElement->setAttribute('xmlns:sig', 'http://www.archyvai.lt/adoc/2008/metadata/signable');
            $mainElement->setAttribute('ID', 'metadata_0');
            $dom->appendChild($mainElement);

            $document = $dom->createElement('sig:document');
            $document->setAttribute('ID', 'document_0');
            $document->appendChild($dom->createElement('sig:title', $this->documentTitle));
            $mainElement->appendChild($document);

            $authors = $dom->createElement('sig:authors');
            $authors->setAttribute('ID', 'authors_0');

            $authorCount = 0;
            foreach ($this->authors as $author) {
                $element = $dom->createElement('sig:author');
                $element->setAttribute('ID', "author_{$authorCount}");
                $element->appendChild($dom->createElement('sig:name', $author['name']));
                if (!empty($author['code'])) $element->appendChild($dom->createElement('sig:code', $author['code']));
                $element->appendChild($dom->createElement('sig:address', $author['address']));
                $element->appendChild($dom->createElement('sig:individual', $author['isIndividual'] ? 'true' : 'false'));
                $authors->appendChild($element);
                $authorCount++;
            }
            $mainElement->appendChild($authors);

            $xmlSignableMetaData[self::METADATA_FOLDER . 'signableMetadata0.xml'] = array(
                'locked' => false,
                'content' => $dom->saveXML()
            );
        }


        foreach ($this->signatures as $signature) {
            $signableMetadataPath = self::METADATA_FOLDER . "signableMetadata{$signature['id']}.xml";
            if (array_key_exists($signableMetadataPath, $this->lockedSignableMetadataFiles)) {
                $xmlSignableMetaData[$signableMetadataPath] = array(
                    'locked' => true,
                    'content' => $this->lockedSignableMetadataFiles[$signableMetadataPath]
                );
                continue;
            }
            $dom = new DOMDocument('1.0', 'utf-8');
            $mainElement = $dom->createElement('sig:metadata');
            $mainElement->setAttribute('xmlns:sig', 'http://www.archyvai.lt/adoc/2008/metadata/signable');
            $mainElement->setAttribute('ID', 'metadata_0');
            $dom->appendChild($mainElement);

            $signatures = $dom->createElement('sig:signatures');
            $mainElement->appendChild($signatures);
            $signatureElem = $dom->createElement('sig:signature');
            $signatureElem->setAttribute('ID', "signature_{$signature['id']}");
            $signatureElem->appendChild($dom->createElement('sig:signatureID', "META-INF/signatures/signatures{$signature['id']}.xml#SignatureElem_{$signature['id']}"));
            $signatureElem->appendChild($dom->createElement('sig:signingTime', $signature['signedDatetime']));
            $signatureElem->appendChild($dom->createElement('sig:signingPurpose', 'signature'));
            $signer = $dom->createElement('sig:signer');
            $signer->appendChild($dom->createElement('sig:individualName', $signature['x509Certificate']['subject']['GN'] . ' ' . $signature['x509Certificate']['subject']['SN']));
            $signer->appendChild($dom->createElement('sig:positionName'));
            $signer->appendChild($dom->createElement('sig:structuralSubdivision'));
            $signatureElem->appendChild($signer);
            $signatures->appendChild($signatureElem);
            $xmlSignableMetaData[$signableMetadataPath] = array(
                'locked' => false,
                'content' => $dom->saveXML()
            );
        }
        return $xmlSignableMetaData;
    }

    /**
     * @return string
     */
    private function generateXmlUnsignableMetadata()
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $mainElement = $dom->createElement('uns:metadata');
        $mainElement->setAttribute('xmlns:uns', 'http://www.archyvai.lt/adoc/2008/metadata/unsignable');
        $mainElement->setAttribute('ID', 'metadata_0');

        $use = $dom->createElement("uns:Use");
        $use->setAttribute('ID', 'use_0');

        $technicalEnvironment = $dom->createElement('uns:technical_environment');
        $technicalEnvironment->setAttribute('ID', 'techEnv_0');
        $technicalEnvironment->appendChild($dom->createElement('uns:standardVersion', self::STANDARD_VERSION));
        $technicalEnvironment->appendChild($dom->createElement('uns:documentCategory', $this->documentCategory));
        $technicalEnvironment->appendChild($dom->createElement('uns:generator', 'PHP'));
        $technicalEnvironment->appendChild($dom->createElement('uns:os', php_uname()));
        $use->appendChild($technicalEnvironment);
        $mainElement->appendChild($use);
        $dom->appendChild($mainElement);
        return $dom->saveXML();
    }

    /**
     * @return string
     */
    private function generateXmlRelations()
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $mainElement = $dom->createElement('Relationships');
        $mainElement->setAttribute('xmlns', self::XMLNS_RELATIONSHIPS);
        $mainElement->setAttribute('xmlns:xsi', self::XMLNS_SCHEMA_INSTANCE);
        $dom->appendChild($mainElement);

        $sourcePartMain = $dom->createElement('SourcePart');
        $sourcePartMain->setAttribute('full-path', "/");
        $relationship = $dom->createElement('Relationship');
        $relationship->setAttribute('full-path', $this->unsignableMetadataPath);
        $relationship->setAttribute('type', self::TYPE_UNSIGNABLE_METADATA);
        $sourcePartMain->appendChild($relationship);
        $relationship = $dom->createElement('Relationship');
        $relationship->setAttribute('full-path', "metadata/signableMetadata0.xml");
        $relationship->setAttribute('type', self::TYPE_SIGNABLE_METADATA);
        $sourcePartMain->appendChild($relationship);

        $relationship = $dom->createElement('Relationship');
        $mainDocumentPath = $this->documentFiles[0]['path'];
        $sourcePartMainDocument = $dom->createElement('SourcePart');
        $sourcePartMainDocument->setAttribute('full-path', $mainDocumentPath);
        $relationship->setAttribute('full-path', $mainDocumentPath);
        $relationship->setAttribute('type', self::TYPE_CONTENT_MAIN);
        $sourcePartMain->appendChild($relationship);
        $mainElement->appendChild($sourcePartMain);

        $sourcePartSignableMetadata = $dom->createElement('SourcePart');
        $sourcePartSignableMetadata->setAttribute('full-path', "metadata/signableMetadata0.xml");

        $sourcePartAppendixList = [];
        if (count($this->documentFiles) > 1) {
            for ($i = 1; $i < count($this->documentFiles); $i++) {
                $sourcePartAppendix = $dom->createElement('SourcePart');
                $sourcePartAppendix->setAttribute('full-path', $this->documentFiles[$i]['path']);
                $sourcePartAppendixList[] = $sourcePartAppendix;

                $relationship = $dom->createElement('Relationship');
                $relationship->setAttribute('full-path', $this->documentFiles[$i]['path']);
                $relationship->setAttribute('type', self::TYPE_CONTENT_APPENDIX);
                $sourcePartMainDocument->appendChild($relationship);

            }
        }

        for ($signatureIndex = 0; $signatureIndex < count($this->signatures); $signatureIndex++) {

            $relationship = $dom->createElement('Relationship');
            $relationship->setAttribute('full-path', "metadata/signableMetadata{$this->signatures[$signatureIndex]['id']}.xml");
            $relationship->setAttribute('type', self::TYPE_SIGNABLE_METADATA);
            $sourcePartMain->appendChild($relationship);

            $relationship = $dom->createElement('Relationship');
            $relationship->setAttribute('full-path', $this->getSignaturePath($this->signatures[$signatureIndex]['id']));
            $relationship->setAttribute('type', self::TYPE_SIGNATURES);
            $sourcePartMain->appendChild($relationship);

            $sourcePartMainDocument->appendChild($relationship->cloneNode());

            foreach ($sourcePartAppendixList as $sourcePartAppendix) {
                $sourcePartAppendix->appendChild($relationship->cloneNode());
            }

            $relationship = $dom->createElement('Relationship');
            $relationship->setAttribute('full-path', $this->getSignaturePath($this->signatures[$signatureIndex]['id']));
            $relationship->setAttribute('type', self::TYPE_SIGNATURES);

            $element = $dom->createElement('Element');
            $element->setAttribute('in-source-part', 'true');
            $element->setAttribute('ref-id', 'document_0');
            $relationship->appendChild($element);

            for ($i = 0; $i < count($this->authors); $i++) {
                $element = $dom->createElement('Element');
                $element->setAttribute('in-source-part', 'true');
                $element->setAttribute('ref-id', "author_{$i}");
                $relationship->appendChild($element);
            }
            $sourcePartSignableMetadata->appendChild($relationship);

            $sourcePart = $dom->createElement('SourcePart');
            $sourcePart->setAttribute('full-path', "metadata/signableMetadata{$this->signatures[$signatureIndex]['id']}.xml");
            $mainElement->appendChild($sourcePart);
            for ($signatureIndex1 = 0; $signatureIndex1 < count($this->signatures); $signatureIndex1++) {
                $relationship = $dom->createElement('Relationship');
                $relationship->setAttribute('full-path', $this->getSignaturePath($this->signatures[$signatureIndex1]['id']));
                $relationship->setAttribute('type', self::TYPE_SIGNATURES);

                if ($signatureIndex == $signatureIndex1) {
                    $element = $dom->createElement('Element');
                    $element->setAttribute('in-source-part', 'true');
                    $element->setAttribute('ref-id', "signature_{$this->signatures[$signatureIndex]['id']}");
                    $relationship->appendChild($element);
                }
                if ($relationship->hasChildNodes())
                    $sourcePart->appendChild($relationship);
            }
        }
        if ($sourcePartSignableMetadata->hasChildNodes())
            $mainElement->appendChild($sourcePartSignableMetadata);

        if ($sourcePartMainDocument->hasChildNodes())
            $mainElement->appendChild($sourcePartMainDocument);

        foreach ($sourcePartAppendixList as $sourcePartAppendix) {
            if ($sourcePartAppendix->hasChildNodes())
                $mainElement->appendChild($sourcePartAppendix);
        }
        return $dom->saveXML();
    }

    /**
     * @param $name
     * @param $address
     * @param $code
     * @param bool $isIndividual
     * @throws ADocFieldMissingException
     * @throws AlreadySignedException
     */
    public function addAuthor($name, $address, $code, $isIndividual = false)
    {
        if ($this->isSigned())
            throw new AlreadySignedException("Cannot add author, because ADoc is already signed.");
        if (empty($name))
            throw new ADocFieldMissingException('Name is required.');
        if (empty($address))
            throw new ADocFieldMissingException('Address is required.');
        if (!$isIndividual && empty($code))
            throw new ADocFieldMissingException('Code cannot be empty, when individual is not using.');
        $this->authors[] = array('name' => trim($name), 'address' => trim($address), 'code' => $code, 'isIndividual' => $isIndividual);
    }

    /**
     * @param $title
     */
    public function setDocumentTitle($title)
    {
        $this->documentTitle = $title;
    }

    /**
     * @inheritdoc
     */
    public function getMimeType()
    {
        return self::MIMETYPE;
    }

    /**
     * @inheritdoc
     */
    protected function parseSignatureXmlData($data)
    {
        $signatureData = parent::parseSignatureXmlData($data);
        if (!preg_match('/\d+$/', $signatureData['xmlId'], $matches)) {
            throw new DigitalDocumentException('Signature Id number is missing');
        }
        $signatureData['id'] = (int)$matches[0];
        return $signatureData;
    }

    /**
     * @inheritdoc
     */
    protected function loading($files)
    {
        if (!array_key_exists($this->relationsPath, $files)) {
            throw new FileMissingException($this->relationsPath);
        }

        $relations = $this->parseRelationsXmlData($files[$this->relationsPath]);

        if (array_key_exists(self::TYPE_SIGNABLE_METADATA, $relations)) {
            $lockSignableMetadata = array();
            $lockedSignableMetadata = false;
            foreach ($relations[self::TYPE_SIGNABLE_METADATA] as $path) {
                if (!array_key_exists($path, $files)) {
                    throw new FileMissingException($path);
                }
                $signableMetadata = $this->parseSignableMetadataXml($files[$path]);
                if (array_key_exists('documentTitle', $signableMetadata)) {
                    $this->documentTitle = $signableMetadata['documentTitle'];
                    $lockSignableMetadata[$path] = 'info';
                }
                if (array_key_exists('authors', $signableMetadata)) {
                    $this->authors = array_merge($this->authors, $signableMetadata['authors']);
                    $lockSignableMetadata[$path] = 'info';
                }
                if (array_key_exists('signature', $signableMetadata)) {
                    foreach ($this->signatures as $signature) {
                        if ($signableMetadata['signature']['id'] == "{$signature['path']}#{$signature['xmlId']}" && $signature['locked']) {
                            $lockSignableMetadata[$path] = 'locked';
                            $lockedSignableMetadata = true;
                            break;
                        }
                    }
                }
            }
            if ($lockedSignableMetadata) {
                foreach ($lockSignableMetadata as $path => $type) {
                    if (in_array($type, ['info', 'locked'])) {
                        $this->lockedSignableMetadataFiles[$path] = $files[$path];
                    }
                }
            }
        }
    }


    /**
     * @param $data
     * @return array
     * @throws XmlMissingException
     */
    private function parseSignableMetadataXml($data)
    {
        $signableMetaData = array();
        $signableMeta = simplexml_load_string($data);
        $title = $signableMeta->xpath('/sig:metadata/sig:document/sig:title');
        if (count($title) != 0)
            $signableMetaData['documentTitle'] = (string)$title[0];

        $authors = $signableMeta->xpath('/sig:metadata/sig:authors/sig:author');
        if (count($authors) != 0) {
            $signableMetaData['authors'] = array();
            foreach ($authors as $author) {
                $authorData = array();
                $name = $author->xpath('sig:name');
                if (count($name) == 0)
                    throw new XmlMissingException("Author name field is missing.");
                $authorData['name'] = (string)$name[0];
                $code = $author->xpath('sig:code');
                if (count($code) == 0)
                    $authorData['code'] = '';
                else
                    $authorData['code'] = (string)$code[0];
                $address = $author->xpath('sig:address');
                if (count($address) == 0)
                    $authorData['address'] = '';
                else
                    $authorData['address'] = (string)$address[0];
                $individual = $author->xpath('sig:individual');
                if (count($individual) == 0)
                    throw new XmlMissingException("Author individual field is missing.");
                $authorData['isIndividual'] = (string)$individual[0] == 'true';
                $signableMetaData['authors'][] = $authorData;
            }
        }
        $signatures = $signableMeta->xpath('/sig:metadata/sig:signatures/sig:signature');
        if (count($signatures) != 0) {
            $signatureData = array();
            foreach ($signatures as $signature) {
                $signatureId = $signature->xpath('sig:signatureID');
                if (count($signatureId) == 0)
                    throw new XmlMissingException("Signature ID is missing.");
                $signatureData['id'] = (string)$signatureId[0];
                $signingTime = $signature->xpath('sig:signingTime');
                if (count($signingTime) == 0)
                    throw new XmlMissingException("Signature signing time is missing.");
                $signatureData['signingTime'] = (string)$signingTime[0];
                $individualName = $signature->xpath('sig:signer/sig:individualName');
                if (count($individualName) == 0)
                    throw new XmlMissingException("Signature individual name is missing.");
                $signatureData['individualName'] = (string)$individualName[0];
            }
            $signableMetaData['signature'] = $signatureData;
        }

        return $signableMetaData;
    }

    protected function parseRelationsXmlData($data)
    {
        $relationList = [];

        $relationsXml = simplexml_load_string($data);
        $relationsXml->registerXPathNamespace('x', self::XMLNS_RELATIONSHIPS);
        $relationships = $relationsXml->xpath('//x:Relationships/x:SourcePart/x:Relationship');
        foreach ($relationships as $relationship) {
            $relationshipType = (string)$relationship['type'];
            $relationshipFullPath = (string)$relationship['full-path'];

            if (array_key_exists($relationshipType, $relationList)) {
                if (!in_array($relationshipFullPath, $relationList[$relationshipType])) {
                    $relationList[$relationshipType][] = $relationshipFullPath;
                }
            } else {
                $relationList[$relationshipType] = [$relationshipFullPath];
            }
        }
        return $relationList;
    }

    /**
     * @inheritdoc
     */
    protected function appending($secondDigitalDocument)
    {
        /** @var self $secondAdoc */
        $secondAdoc = $secondDigitalDocument;

        $differentAuthors = array();
        $sameAuthorCount = 0;
        foreach ($secondAdoc->authors as $author) {
            $foundSameAuthor = false;
            foreach ($this->authors as $currentAuthor) {
                if ($currentAuthor['name'] == $author['name'] && $currentAuthor['address'] == $author['address'] && $currentAuthor['code'] == $author['code'] && $currentAuthor['isIndividual'] == $author['isIndividual']) {
                    $foundSameAuthor = true;
                    break;
                }
            }
            if ($foundSameAuthor)
                $sameAuthorCount++;
            else
                $differentAuthors[] = $author;
        }

        if ($this->isSigned() && $sameAuthorCount != count($this->authors)) {
            throw new AppendException("ADoc could not append file, because file was signed.");
        }

        $newSignableMetadata = array();
        foreach ($secondAdoc->lockedSignableMetadataFiles as $path => $content) {
            if (array_key_exists($path, $this->lockedSignableMetadataFiles)) {
                if (md5($this->lockedSignableMetadataFiles[$path]) != md5($content)) {
                    throw new AppendException("Signable metadata is not matched.");
                }
            } else {
                $newSignableMetadata[$path] = $content;
            }
        }

        foreach ($differentAuthors as $author) {
            $this->authors[] = $author;
        }
        foreach ($newSignableMetadata as $path => $content) {
            $this->lockedSignableMetadataFiles[$path] = $content;
        }
    }
}