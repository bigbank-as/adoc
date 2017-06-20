<?php
use Bigbank\XadesDocument\ADoc;
use PHPUnit\Framework\TestCase;


abstract class BaseADocTest extends TestCase
{
    use HasReflectionClass;

    protected $tempFiles = array();

    protected function setUp()
    {
        parent::setUp();
        $tempFiles = array();
    }

    protected function tearDown()
    {
        parent::tearDown();
        foreach ($this->tempFiles as $file){
            @unlink($file);
        }
    }

    /**
     * @return string
     */
    protected function createTempFile()
    {
        $tempFile = tempnam('/tmp', 'adoc');
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }

    /**
     * @param $manifestData
     * @param array $requried
     */
    protected function checkManifest($manifestData, $requried){
        $adoc = new ADoc();
        $manifest = $this->invokeMethod($adoc, 'parseManifest', $manifestData);
        foreach ($manifest['fileEntries'] as $fileEntry){
            foreach ($requried as $key => $r){
                if($r['full-path'] == $fileEntry['full-path'] && $r['media-type'] == $fileEntry['media-type']){
                    unset($requried[$key]);
                    break;
                }
            }
        }
        $this->assertEmpty($requried);
    }
}