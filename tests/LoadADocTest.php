<?php
use Bigbank\XadesDocument\ADoc;

final class LoadADocTest extends BaseADocTest
{
    use HasPdfRandom;

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\InvalidMimetypeException
     */
    public function testLoadInvalidMimetype()
    {
        // Create file
        $zipArchive = new ZipArchive();
        $adocFile = $this->createTempFile();
        $zipArchive->open($adocFile, ZipArchive::CREATE);
        $zipArchive->addFromString('mimetype', ADoc::MIMETYPE . '-invalid-mimetype');
        $zipArchive->close();

        // Open ADoc file
        new ADoc($adocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\FileMissingException
     */
    public function testLoadMissingFileManifest()
    {
        // Create file
        $zipArchive = new ZipArchive();
        $adocFile = $this->createTempFile();
        $zipArchive->open($adocFile, ZipArchive::CREATE);
        $zipArchive->addFromString('mimetype', ADoc::MIMETYPE);
        $zipArchive->close();

        // Open ADoc file
        new ADoc($adocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\FileMissingException
     */
    public function testLoadMissingFileDocument()
    {
        // Create file
        $zipArchive = new ZipArchive();
        $adocFile = $this->createTempFile();
        $zipArchive->open($adocFile, ZipArchive::CREATE);
        $zipArchive->addFromString('mimetype', ADoc::MIMETYPE);
        $zipArchive->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="utf-8"?>
        <manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
          <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008"/>
          <manifest:file-entry manifest:full-path="Test1.pdf" manifest:media-type="application/pdf"/>
          <manifest:file-entry manifest:full-path="META-INF/" manifest:media-type=""/>
          <manifest:file-entry manifest:full-path="metadata/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008#metadata-folder"/>
        </manifest:manifest>');
        $zipArchive->close();

        // Open ADoc file
        new ADoc($adocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\FileMissingException
     */
    public function testLoadMissingFileRelations()
    {
        // Create file
        $zipArchive = new ZipArchive();
        $adocFile = $this->createTempFile();
        $zipArchive->open($adocFile, ZipArchive::CREATE);
        $zipArchive->addFromString('mimetype', ADoc::MIMETYPE);
        $zipArchive->addFromString('Test1.pdf', $this->getPDFDocumentContent());
        $zipArchive->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="utf-8"?>
        <manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
          <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008"/>
          <manifest:file-entry manifest:full-path="Test1.pdf" manifest:media-type="application/pdf"/>
          <manifest:file-entry manifest:full-path="META-INF/" manifest:media-type=""/>
          <manifest:file-entry manifest:full-path="metadata/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008#metadata-folder"/>
          <manifest:file-entry manifest:full-path="META-INF/relations.xml" manifest:media-type="text/xml"/>
        </manifest:manifest>');
        $zipArchive->close();

        // Open ADoc file
        new ADoc($adocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\FileMissingException
     */
    public function testLoadMissingFileSignableMetadata()
    {
        // Create file
        $zipArchive = new ZipArchive();
        $adocFile = $this->createTempFile();
        $zipArchive->open($adocFile, ZipArchive::CREATE);
        $zipArchive->addFromString('mimetype', ADoc::MIMETYPE);
        $zipArchive->addFromString('Test1.pdf', $this->getPDFDocumentContent());
        $zipArchive->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="utf-8"?>
        <manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
          <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008"/>
          <manifest:file-entry manifest:full-path="Test1.pdf" manifest:media-type="application/pdf"/>
          <manifest:file-entry manifest:full-path="META-INF/" manifest:media-type=""/>
          <manifest:file-entry manifest:full-path="metadata/" manifest:media-type="application/vnd.lt.archyvai.adoc-2008#metadata-folder"/>
          <manifest:file-entry manifest:full-path="META-INF/relations.xml" manifest:media-type="text/xml"/>
        </manifest:manifest>');
        $zipArchive->addFromString('META-INF/relations.xml', '<?xml version="1.0" encoding="utf-8"?>
        <Relationships xmlns="http://www.archyvai.lt/adoc/2008/relationships" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
          <SourcePart full-path="/">
            <Relationship full-path="metadata/signableMetadata0.xml" type="http://www.archyvai.lt/adoc/2008/relationships/metadata/signable"/>
            <Relationship full-path="Test1.pdf" type="http://www.archyvai.lt/adoc/2008/relationships/content/main"/>
          </SourcePart>
        </Relationships>
        ');
        $zipArchive->close();

        // Open ADoc file
        new ADoc($adocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\DigitalDocumentCouldNotOpenFileException
     */
    public function testLoadFailed()
    {
        new ADoc(__DIR__ . '/files/this-file-not-exist.adoc');
    }

    public function testLoad()
    {
        $adoc = new ADoc(file_get_contents(__DIR__ . '/files/signed.adoc'));
        $this->assertTrue($adoc->isSignedForComplete());
        $authors = $this->getPropertyValue($adoc, 'authors');
        $this->assertNotEmpty($authors);
        $this->assertCount(1, $authors);
        $this->assertEquals($authors[0]['name'], 'Bigbank');
        $this->assertEquals($authors[0]['address'], 'Jogailos g 4');
        $this->assertEquals($authors[0]['code'], '301048563');
        $this->assertFalse($authors[0]['isIndividual']);
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertNotEmpty($signatures);
        $this->assertCount(2, $signatures);

        $this->assertArrayHasKey('locked', $signatures[0]);
        $this->assertTrue($signatures[0]['locked']);
        $this->assertArrayHasKey('signatureData', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['signatureData']);

        $this->assertArrayHasKey('certificateData', $signatures[0]);
        $this->assertStringStartsWith('-----BEGIN CERTIFICATE-----', $signatures[0]['certificateData']);
        $this->assertStringEndsWith('-----END CERTIFICATE-----', $signatures[0]['certificateData']);

        $this->assertArrayHasKey('x509Certificate', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['x509Certificate']);

        $this->assertArrayHasKey('signedDatetime', $signatures[0]);
        $this->assertEquals($signatures[0]['signedDatetime'], '2017-06-07T14:38:54+03:00');

        $this->assertArrayHasKey('canonicalizationMethod', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['canonicalizationMethod']);
        $this->assertEquals($signatures[0]['canonicalizationMethod'], ADoc::C14N);

        $this->assertArrayHasKey('algorithm', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['algorithm']);
        $this->assertEquals($signatures[0]['algorithm'], ADoc::RSA_SHA256);

        $this->assertArrayHasKey('referenceCanonicalizationMethod', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['referenceCanonicalizationMethod']);
        $this->assertEquals($signatures[0]['referenceCanonicalizationMethod'], ADoc::C14N);

        $this->assertArrayHasKey('certAlgorithm', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['certAlgorithm']);
        $this->assertEquals($signatures[0]['certAlgorithm'], ADoc::SHA256);

        $this->assertArrayHasKey('id', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['id']);
        $this->assertEquals($signatures[0]['id'], 1);

        $this->assertArrayHasKey('locked', $signatures[1]);
        $this->assertTrue($signatures[1]['locked']);

        $this->assertArrayHasKey('signedDatetime', $signatures[1]);
        $this->assertEquals($signatures[1]['signedDatetime'], '2017-06-08T18:38:49+03:00');

        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertNotEmpty($documentFiles);
        $this->assertCount(1, $documentFiles);
        $this->assertEquals($documentFiles[0]['path'], 'Test1.pdf');
        $this->assertEquals(md5($documentFiles[0]['content']), '3c17fe6da85017dca0e3e3acee7946eb');
    }
}