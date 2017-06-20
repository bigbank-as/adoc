<?php
use Bigbank\XadesDocument\ADoc;

final class AppendADocTest extends BaseADocTest
{
    use HasPdfRandom;


    public function testAppendOnlyDocumentsAndAuthors()
    {
        // Test create
        $adoc = new ADoc();

        // Test append same ADoc
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adocFile = $this->createTempFile();
        $adoc->save($adocFile);

        $adoc->appendFileOrContent($adocFile);
        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertCount(1, $documentFiles);

        $authors = $this->getPropertyValue($adoc, 'authors');
        $this->assertCount(1, $authors);

        // Test append different doument and same author
        $secondAdoc = new ADoc();
        $secondPdfContent = $this->getPDFDocumentContent('This is second document');
        $secondAdoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addDocument('test1.pdf', $secondPdfContent);
        $secondAdocFile = $this->createTempFile();
        $secondAdoc->save($secondAdocFile);

        $adoc->appendFileOrContent($secondAdocFile);
        $documentFiles = $this->getPropertyValue($adoc, 'documentFiles');
        $this->assertCount(2, $documentFiles);

        $this->assertEquals(md5($documentFiles[1]['content']), md5($secondPdfContent));
        $this->assertNotEquals($documentFiles[1]['path'], 'test1.pdf');

        $authors = $this->getPropertyValue($adoc, 'authors');
        $this->assertCount(1, $authors);

        // Test append different author
        $secondAdoc->addAuthor('Second name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->save($secondAdocFile);
        $adoc->appendFileOrContent($secondAdocFile);

        $authors = $this->getPropertyValue($adoc, 'authors');
        $this->assertCount(2, $authors);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\AppendException
     */
    public function testAppendSignedDifferentDocuments()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $adoc->setLastSignatureData('c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI');
        $adocFile = $this->createTempFile();
        $adoc->save($adocFile);
        $adoc->appendFileOrContent($adocFile);

        $secondAdoc = new ADoc();
        $secondPdfContent = $this->getPDFDocumentContent('This is second document');
        $secondAdoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addDocument('test1.pdf', $secondPdfContent);
        $secondAdocFile = $this->createTempFile();
        $secondAdoc->save($secondAdocFile);
        $adoc->appendFileOrContent($secondAdocFile);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\AppendException
     */
    public function testAppendSignedDifferentAuthors()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $adoc->setLastSignatureData('c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI');
        $adocFile = $this->createTempFile();
        $adoc->save($adocFile);
        $adoc->appendFileOrContent($adocFile);

        $secondAdoc = new ADoc();
        $secondAdoc->addAuthor('Second name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addDocument('test1.pdf', $pdfContent);
        $secondAdocFile = $this->createTempFile();
        $secondAdoc->save($secondAdocFile);
        $adoc->appendFileOrContent($secondAdocFile);
    }


    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\AppendException
     */
    public function testAppendSignedDifferentSignatureButIdSame()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $adoc->setLastSignatureData('c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI');


        $secondAdoc = new ADoc();
        $secondAdoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addDocument('test1.pdf', $pdfContent);

        $secondAdoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $secondAdoc->setLastSignatureData('dsfdsfsda');

        $secondAdocFile = $this->createTempFile();
        $secondAdoc->save($secondAdocFile);
        $adoc->appendFileOrContent($secondAdocFile);
    }

    public function testAppendSignedDifferentSignature()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addDocument('test2.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addAuthor('Second name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $adoc->setLastSignatureData('c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI');


        $secondAdoc = new ADoc();
        $secondAdoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addAuthor('Second name', 'Viru 4, Tallinn, Estonia', '10201');
        $secondAdoc->addDocument('test1.pdf', $pdfContent);
        $secondAdoc->addDocument('test2.pdf', $pdfContent);
        $secondAdoc->addPrepareSignature('MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
                MQswCQYDVQQGEwJFRTEiMCAGA1UECgwZQVMgU2VydGlmaXRzZWVyaW1pc2tlc2t1
                czEfMB0GA1UEAwwWVEVTVCBvZiBFU1RFSUQtU0sgMjAxMTEYMBYGCSqGSIb3DQEJ
                ARYJcGtpQHNrLmVlMB4XDTE0MTIwOTE1MjYyMFoXDTE3MTIwOTIxNTk1OVowgb8x
                CzAJBgNVBAYTAkVFMRswGQYDVQQKDBJFU1RFSUQgKE1PQklJTC1JRCkxFzAVBgNV
                BAsMDmF1dGhlbnRpY2F0aW9uMTIwMAYDVQQDDClP4oCZQ09OTkXFvS3FoFVTTElL
                LE1BUlkgw4ROTiwxMTQxMjA5MDAwNDEcMBoGA1UEBAwTT+KAmUNPTk5Fxb0txaBV
                U0xJSzESMBAGA1UEKgwJTUFSWSDDhE5OMRQwEgYDVQQFEwsxMTQxMjA5MDAwNDBZ
                MBMGByqGSM49AgEGCCqGSM49AwEHA0IABHYleZg39CkgQGU8z8b8ehctBEnaGldu
                cij6eTETeOj2LpEwLedMS1pCfNEZAJjDwAZ2DJMBgB05QHrrvzersUKjggGsMIIB
                qDAJBgNVHRMEAjAAMA4GA1UdDwEB/wQEAwIEsDCBmQYDVR0gBIGRMIGOMIGLBgor
                BgEEAc4fAwMBMH0wWAYIKwYBBQUHAgIwTB5KAEEAaQBuAHUAbAB0ACAAdABlAHMA
                dABpAG0AaQBzAGUAawBzAC4AIABPAG4AbAB5ACAAZgBvAHIAIAB0AGUAcwB0AGkA
                bgBnAC4wIQYIKwYBBQUHAgEWFWh0dHA6Ly93d3cuc2suZWUvY3BzLzAsBgNVHREE
                JTAjgSFtYXJ5LmFubi5vLmNvbm5lei1zdXNsaWtAZWVzdGkuZWUwHQYDVR0OBBYE
                FJ3eqIvcJ/uIUPi7T7xHWlzOZM/oMCAGA1UdJQEB/wQWMBQGCCsGAQUFBwMCBggr
                BgEFBQcDBDAYBggrBgEFBQcBAwQMMAowCAYGBACORgEBMB8GA1UdIwQYMBaAFEG2
                /sWxsbRTE4z6+mLQNG1tIjQKMEUGA1UdHwQ+MDwwOqA4oDaGNGh0dHA6Ly93d3cu
                c2suZWUvcmVwb3NpdG9yeS9jcmxzL3Rlc3RfZXN0ZWlkMjAxMS5jcmwwDQYJKoZI
                hvcNAQELBQADggEBALxS9kBbIvUKLKbbxx8oCkzjx3Y30DsnkFYGxLklx5x4Uh0P
                q6nieuiwiYKNgXonOksz+NJ9hOepGdFwCGMdm2getYIGbOv1dOswJVq+ygABGj0w
                vCVT1CO530cXL3gY4aXOmFsGnpqkr0r4pyaMVVlovgjEnFeadw/0d5nT9EfptJNx
                kfBq3WWqaslPRZAhutZzcionO83nugmfEYvTeucvF+odpj12HARZK79Iw74L1C3r
                HTNDYki7wGUzc4hU+LuTldSX4lTMI3mq4K0w/8VaE5XKcU6YJP0h+pE44d9Ay5yL
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=');
        $secondAdoc->setLastSignatureId(2);
        $secondAdoc->setLastSignatureData('dsfdsfsda');

        $secondAdocFile = $this->createTempFile();
        $secondAdoc->save($secondAdocFile);
        $adoc->appendFileOrContent($secondAdocFile);

        $appenedAdocFile = $this->createTempFile();
        $adoc->save($appenedAdocFile);

        $zipArchive = new ZipArchive();
        $zipArchive->open($appenedAdocFile);
        $signature1 = $zipArchive->getFromName('META-INF/signatures/signatures1.xml');
        $this->assertNotEmpty($signature1);
        $signature2 = $zipArchive->getFromName('META-INF/signatures/signatures2.xml');
        $this->assertNotEmpty($signature2);
        $this->assertNotEquals(md5($signature1), md5($signature2));
    }

}