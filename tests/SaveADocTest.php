<?php
use Bigbank\XadesDocument\ADoc;

final class SaveADocTest extends BaseADocTest
{
    use HasPdfRandom;

    public function testSave()
    {
        // Test create
        $adoc = new ADoc();

        // Test add document
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adocFile = $this->createTempFile();
        $adoc->setDocumentTitle('This test Adoc for saving');
        $adoc->save($adocFile);
        $this->assertFileExists($adocFile);

        // Test second save
        $firstSavedFileMd5 = md5(file_get_contents($adocFile));
        $adoc = new ADoc($adocFile);
        $adoc->addAuthor('Second name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->save();

        // Test check
        $secondSaveFileMd5 = md5(file_get_contents($adocFile));
        $this->assertNotEquals($firstSavedFileMd5, $secondSaveFileMd5);

        $zipArchive = new ZipArchive();
        $zipArchive->open($adocFile);
        $mimetype = $zipArchive->getFromName('mimetype');
        $this->assertNotEmpty($mimetype);
        $this->assertEquals($mimetype, ADoc::MIMETYPE);
        $manifest = $zipArchive->getFromName('META-INF/manifest.xml');
        $this->assertNotEmpty($manifest);
        $this->checkManifest(
            $manifest,
            array(
                array(
                    'full-path' => '/',
                    'media-type' => ADoc::MIMETYPE,
                ),
                array(
                    'full-path' => 'test1.pdf',
                    'media-type' => 'application/pdf',
                ),
                array(
                    'full-path' => 'META-INF/',
                    'media-type' => '',
                ),
                array(
                    'full-path' => 'metadata/',
                    'media-type' => ADoc::MIMETYPE . '#metadata-folder',
                ),
                array(
                    'full-path' => 'metadata/unsignableMetadata0.xml',
                    'media-type' => 'text/xml',
                ),
                array(
                    'full-path' => 'metadata/signableMetadata0.xml',
                    'media-type' => 'text/xml',
                ),
                array(
                    'full-path' => 'META-INF/relations.xml',
                    'media-type' => 'text/xml',
                )
            )
        );

        // Test document
        $documentPdf = $zipArchive->getFromName('test1.pdf');
        $this->assertNotEmpty($documentPdf);
        $this->assertEquals(md5($documentPdf), md5($pdfContent));

        $documentPdf = $zipArchive->getFromName('test1.pdf');
        $this->assertNotEmpty($documentPdf);

        $unsignableMetadata = $zipArchive->getFromName('metadata/unsignableMetadata0.xml');
        $this->assertNotEmpty($unsignableMetadata);

        $signableMetadata = $zipArchive->getFromName('metadata/signableMetadata0.xml');
        $this->assertNotEmpty($signableMetadata);
        $this->assertXmlStringEqualsXmlString($signableMetadata, '<sig:metadata xmlns:sig="http://www.archyvai.lt/adoc/2008/metadata/signable" ID="metadata_0"><sig:document ID="document_0"><sig:title>This test Adoc for saving</sig:title></sig:document><sig:authors ID="authors_0"><sig:author ID="author_0"><sig:name>First name</sig:name><sig:code>10201</sig:code><sig:address>Viru 4, Tallinn, Estonia</sig:address><sig:individual>false</sig:individual></sig:author><sig:author ID="author_1"><sig:name>Second name</sig:name><sig:code>10201</sig:code><sig:address>Viru 4, Tallinn, Estonia</sig:address><sig:individual>false</sig:individual></sig:author></sig:authors></sig:metadata>');

        $relations = $zipArchive->getFromName('META-INF/relations.xml');
        $this->assertNotEmpty($relations);

        $this->assertXmlStringEqualsXmlString($relations, '<Relationships xmlns="http://www.archyvai.lt/adoc/2008/relationships" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
          <SourcePart full-path="/">
            <Relationship full-path="metadata/unsignableMetadata0.xml" type="http://www.archyvai.lt/adoc/2008/relationships/metadata/unsignable"/>
            <Relationship full-path="metadata/signableMetadata0.xml" type="http://www.archyvai.lt/adoc/2008/relationships/metadata/signable"/>
            <Relationship full-path="test1.pdf" type="http://www.archyvai.lt/adoc/2008/relationships/content/main"/>
          </SourcePart>
        </Relationships>');
        $zipArchive->close();

        // Test create
        $adoc = new ADoc();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adocContent = $adoc->save();
        $this->assertLessThan(strlen($adocContent), 2048);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\DocumentEmptiedException
     */
    public function testSaveWithEmptiedDocuments(){
        $adoc = new ADoc();
        $adoc->save($this->createTempFile());
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\ADoc\ADocAuthorEmptiedException
     */
    public function testSaveWithEmptiedAuthors(){
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->save($this->createTempFile());
    }
}