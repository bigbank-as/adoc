<?php

use Bigbank\XadesDocument\ADoc;

final class CreateADocTest extends BaseADocTest
{
    use HasPdfRandom;


    public function testAddDocument()
    {
        // Test create
        $adoc = new ADoc();

        // Test add document
        $adoc->addDocument('test1.pdf', $this->getPDFDocumentContent());
        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertNotEmpty($documentFiles);

        // Test add documents
        $adoc->addDocument('test1.pdf', $this->getPDFDocumentContent());
        $adoc->addDocument('test1.pdf', $this->getPDFDocumentContent());
        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertCount(3, $documentFiles);
        $this->assertFalse($documentFiles[1]['path'] == $documentFiles[2]['path'], 'Document path must be different.');
    }


    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\InvalidMimetypeException
     */
    public function testAddInvalidDocument()
    {
        // Test create
        $adoc = new ADoc();
        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertEmpty($documentFiles);

        // Test add invalid document
        $adoc->addDocument('test1.exe', $this->getPDFDocumentContent());
    }

    public function testAddAuthor(){
        // Test create
        $adoc = new ADoc();

        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addAuthor('Second name ', 'Viru 5, Tallinn, Estonia', 10200, true);
        $adoc->addAuthor('Third name ', 'Viru 6, Tallinn, Estonia', '', true);
        $authors = $this->getPropertyValue($adoc, 'authors');
        $this->assertNotEmpty($authors);
        $this->assertCount(3, $authors);
        // Test first author
        $this->assertEquals($authors[0]['name'], 'First name');
        $this->assertEquals($authors[0]['address'], 'Viru 4, Tallinn, Estonia');
        $this->assertEquals($authors[0]['code'], '10201');
        $this->assertFalse($authors[0]['isIndividual']);

        // Test second author
        $this->assertEquals($authors[1]['name'], 'Second name');
        $this->assertEquals($authors[1]['address'], 'Viru 5, Tallinn, Estonia');
        $this->assertEquals($authors[1]['code'], '10200');
        $this->assertTrue($authors[1]['isIndividual']);

        // Test third author
        $this->assertEquals($authors[2]['name'], 'Third name');
        $this->assertEquals($authors[2]['address'], 'Viru 6, Tallinn, Estonia');
        $this->assertEmpty($authors[2]['code']);
        $this->assertTrue($authors[2]['isIndividual']);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\ADoc\ADocFieldMissingException
     */
    public function testAddAuthorEmptyName(){
        // Test create
        $adoc = new ADoc();
        $adoc->addAuthor('', 'Viru 4, Tallinn, Estonia', '10201');
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\ADoc\ADocFieldMissingException
     */
    public function testAddAuthorEmptyAddress(){
        // Test create
        $adoc = new ADoc();
        $adoc->addAuthor('Test name', '', '10201');
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\ADoc\ADocFieldMissingException
     */
    public function testAddAuthorEmptyCodeAndNotIndividual(){
        // Test create
        $adoc = new ADoc();
        $adoc->addAuthor('Test name', 'Viru 4, Tallinn, Estonia', '', false);
    }

}