<?php
use Bigbank\XadesDocument\ADoc;

final class SignatureADocTest extends BaseADocTest
{
    use HasPdfRandom;


    public function testAddPrepareSignature()
    {
        $adoc = new ADoc();
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertEmpty($signatures);
        $this->assertFalse($adoc->isSignedForComplete());
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $cert = 'MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
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
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=';
        $firstPepareSignatureId = $adoc->addPrepareSignature($cert);
        $this->assertEquals($firstPepareSignatureId, 1);

        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertNotEmpty($signatures);
        $this->assertCount(1, $signatures);
        $this->assertArrayHasKey('locked', $signatures[0]);
        $this->assertFalse($signatures[0]['locked']);
        $this->assertEquals($adoc->isLastSignatureLocked(), $signatures[0]['locked']);

        $this->assertArrayHasKey('signatureData', $signatures[0]);
        $this->assertEmpty($signatures[0]['signatureData']);

        $this->assertArrayHasKey('certificateData', $signatures[0]);
        $this->assertStringStartsWith('-----BEGIN CERTIFICATE-----', $signatures[0]['certificateData']);
        $this->assertStringEndsWith('-----END CERTIFICATE-----', $signatures[0]['certificateData']);
        $this->assertEquals($adoc->getLastSignatureCertificate(),$signatures[0]['certificateData']);

        $this->assertArrayHasKey('x509Certificate', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['x509Certificate']);

        $this->assertArrayHasKey('signedDatetime', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['signedDatetime']);

        $this->assertArrayHasKey('canonicalizationMethod', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['canonicalizationMethod']);
        $this->assertEquals($signatures[0]['canonicalizationMethod'], "http://www.w3.org/TR/2001/REC-xml-c14n-20010315");
        $this->assertEquals($adoc->getLastSignatureCanonicalizationMethod(), $signatures[0]['canonicalizationMethod']);

        $this->assertArrayHasKey('referenceAlgorithm', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['referenceAlgorithm']);
        $this->assertEquals($signatures[0]['referenceAlgorithm'], "http://www.w3.org/2001/04/xmlenc#sha256");
        $this->assertEquals($adoc->getLastSignatureReferenceAlgorithm(), $signatures[0]['referenceAlgorithm']);

        $this->assertArrayHasKey('algorithm', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['algorithm']);
        $this->assertEquals($signatures[0]['algorithm'], "http://www.w3.org/2000/09/xmldsig#rsa-sha1");
        $this->assertEquals($adoc->getLastSignatureAlgorithm(), $signatures[0]['algorithm']);

        $this->assertArrayHasKey('referenceCanonicalizationMethod', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['referenceCanonicalizationMethod']);
        $this->assertEquals($signatures[0]['referenceCanonicalizationMethod'], "http://www.w3.org/TR/2001/REC-xml-c14n-20010315");
        $this->assertEquals($adoc->getLastSignatureReferenceCanonicalizationMethod(), $signatures[0]['referenceCanonicalizationMethod']);

        $this->assertArrayHasKey('certAlgorithm', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['certAlgorithm']);
        $this->assertEquals($signatures[0]['certAlgorithm'], "http://www.w3.org/2001/04/xmlenc#sha256");
        $this->assertEquals($adoc->getLastSignatureCertAlgorithm(),$signatures[0]['certAlgorithm']);

        $this->assertArrayHasKey('id', $signatures[0]);
        $this->assertNotEmpty($signatures[0]['id']);
        $this->assertEquals($signatures[0]['id'], $firstPepareSignatureId);

        $adoc->setSignatureCanonicalizationMethod(ADoc::C14N_COMMENTS);
        $adoc->setSignatureAlgorithm(ADoc::RSA_SHA256);
        $adoc->setSignatureCertAlgorithm(ADoc::SHA384);
        $adoc->setSignatureReferenceAlgorithm(ADoc::SHA512);
        $adoc->setSignatureReferenceCanonicalizationMethod(ADoc::EXC_C14N);

        $secondPepareSignatureId = $adoc->addPrepareSignature($cert, '2017-06-12T09:17:58+00:00');
        $this->assertEquals($secondPepareSignatureId, 2);
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertCount(2, $signatures);
        $this->assertEquals($signatures[1]['id'], $secondPepareSignatureId);
        $this->assertEquals($adoc->isSignatureLockedById($secondPepareSignatureId), false);
        $this->assertEquals($signatures[1]['canonicalizationMethod'], ADoc::C14N_COMMENTS);
        $this->assertEquals($adoc->getSignatureCanonicalizationMethodById($secondPepareSignatureId),$signatures[1]['canonicalizationMethod']);
        $this->assertEquals($signatures[1]['algorithm'], ADoc::RSA_SHA256);
        $this->assertEquals($adoc->getSignatureAlgorithmById($secondPepareSignatureId), $signatures[1]['algorithm']);
        $this->assertEquals($signatures[1]['referenceCanonicalizationMethod'], ADoc::EXC_C14N);
        $this->assertEquals($adoc->getSignatureReferenceCanonicalizationMethodById($secondPepareSignatureId), $signatures[1]['referenceCanonicalizationMethod']);
        $this->assertEquals($signatures[1]['referenceAlgorithm'], ADoc::SHA512);
        $this->assertEquals($adoc->getSignatureReferenceAlgorithmById($secondPepareSignatureId), $signatures[1]['referenceAlgorithm']);
        $this->assertEquals($signatures[1]['signedDatetime'], '2017-06-12T09:17:58+00:00');
        $this->assertEquals($adoc->getSignatureCertAlgorithmById($secondPepareSignatureId),$signatures[1]['certAlgorithm']);
        $this->assertEquals($adoc->getSignatureCertificateById($secondPepareSignatureId), $signatures[1]['certificateData']);

        // Test set last signature id
        $adoc->setLastSignatureId(100);
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertEquals($signatures[1]['id'], 100);

        // Test set old to new id
        $adoc->setSignatureId(100, 86);
        $adoc->setSignatureId($firstPepareSignatureId, 64);
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertEquals($signatures[0]['id'], 64);
        $this->assertEquals($signatures[1]['id'], 86);

        // Test hash request
        $firstRequestSignatureHash = $adoc->getRequestSignatureHash(64);
        $this->assertNotEmpty($firstRequestSignatureHash);
        $this->assertTrue(strlen($firstRequestSignatureHash) == 40, 'SHA1');
        $secondRequestSignatureHash = $adoc->getRequestSignatureHash(86);
        $this->assertTrue(strlen($secondRequestSignatureHash) == 64, 'SHA256');

        // Test check, file is not signed
        $this->assertFalse($adoc->isSigned());

        // Test get signers.
        $signers = $adoc->getSigners();
        $this->assertEmpty($signers);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureEmptiedException
     */
    public function testGetLastSignatureCertificateEmptiedSignature()
    {
        $adoc = new ADoc();
        $adoc->getLastSignatureCertificate();
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureEmptiedException
     */
    public function testSetLastIdEmptiedSignature()
    {
        $adoc = new ADoc();
        $adoc->setLastSignatureId(32);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureEmptiedException
     */
    public function testSetIdEmptiedSignature()
    {
        $adoc = new ADoc();
        $adoc->setSignatureId(1,32);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureEmptiedException
     */
    public function testSetLastSignatureDataEmptiedSignature()
    {
        $adoc = new ADoc();
        $adoc->setLastSignatureData('ssssss');
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureLockedException
     */
    public function testSetIdLockedSignature()
    {
        $adoc = new ADoc(__DIR__ . '/files/signed.adoc');
        $adoc->setSignatureId(1,32);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureLockedException
     */
    public function testSetSignatureDataLockedSignature()
    {
        $adoc = new ADoc(__DIR__ . '/files/signed.adoc');
        $adoc->setLastSignatureData('sdasffsd');
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\SignatureEmptiedException
     */
    public function testGetRequestSignatureHashEmptiedSignature()
    {
        $adoc = new ADoc();
        $adoc->getLastSignatureRequestHash();
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\NotFoundSignatureIdException
     */
    public function testSetIdNotFoundSignature()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $cert = 'MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
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
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=';
        $firstPepareSignatureId = $adoc->addPrepareSignature($cert);
        $adoc->setSignatureId($firstPepareSignatureId + 2,32);
    }

    public function testSetSignatureData()
    {
        $adoc = new ADoc();
        $signatures = $this->getPropertyValue($adoc, 'signatures');
        $this->assertEmpty($signatures);
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $adoc->setSignatureAlgorithm(ADoc::RSA_SHA512);
        $adoc->setSignatureCertAlgorithm(ADoc::RIPEMD160);
        $adoc->setSignatureReferenceCanonicalizationMethod(ADoc::EXC_C14N_COMMENTS);
        $cert = 'MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
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
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=';
        $adoc->addPrepareSignature($cert);
        $this->assertFalse($adoc->isSignedForComplete());
        $adoc->setSignatureData("signed-data-{$adoc->getLastSignatureRequestHash()}", $cert);
        $this->assertTrue($adoc->isSigned());
        $this->assertTrue($adoc->isSignedForComplete());

        $adoc->setSignatureAlgorithm(ADoc::RSA_RIPEMD160);
        $adoc->addPrepareSignature($cert);
        $this->assertFalse($adoc->isSignedForComplete());
        $adoc->setLastSignatureData("signed-data-{$adoc->getLastSignatureRequestHash()}");
        $this->assertTrue($adoc->isSignedForComplete());

        $adoc->setSignatureAlgorithm(ADoc::RSA_RIPEMD160);
        $thirdSignatureId = $adoc->addPrepareSignature($cert);
        $this->assertFalse($adoc->isSignedForComplete());
        $adoc->setSignatureDataById("signed-data-{$adoc->getRequestSignatureHash($thirdSignatureId)}", $thirdSignatureId);
        $this->assertTrue($adoc->isSignedForComplete());

        $signers = $adoc->getSigners();
        $this->assertNotEmpty($signers);
        $this->assertCount(3, $signers);
        $this->assertNotEquals($signers[0]['id'], $signers[1]['id']);
        $this->assertNotEquals($signers[1]['id'], $signers[2]['id']);
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\CertificateNotMatchedException
     */
    public function testSetSignatureDataCertificateNotMatched()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $cert = 'MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
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
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=';
        $adoc->addPrepareSignature($cert);
        $adoc->setSignatureData("signed-data-failed", "{$cert}-is-not-matched");
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\AlreadySignedException
     */
    public function testGetRequestSignatureHashAlreadySigned()
    {
        $adoc = new ADoc();
        $pdfContent = $this->getPDFDocumentContent();
        $adoc->addDocument('test1.pdf', $pdfContent);
        $adoc->addAuthor('First name', 'Viru 4, Tallinn, Estonia', '10201');
        $cert = 'MIIEmTCCA4GgAwIBAgIQAPVvrDwlATtUhxSc97Dd6zANBgkqhkiG9w0BAQsFADBs
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
                fgitMjnjgMnQHZNyvdoA3yXV3QYYSKSAqUYQy6w=';
        $adoc->addPrepareSignature($cert);
        $adoc->setSignatureData("signed-data-failed{$adoc->getLastSignatureRequestHash()}", "{$cert}");
        $signedAdocFile = $this->createTempFile();
        $adoc->save($signedAdocFile);
        $adoc = new ADoc($signedAdocFile);
        $adoc->getLastSignatureRequestHash();
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\UnknownCanonicalizationMethodException
     */
    public function testSetSignatureUnknownCanonicalizationMethod(){
        $adoc = new ADoc();
        $adoc->setSignatureCanonicalizationMethod('ddasssaa');
    }

    /**
     * @expectedException Bigbank\XadesDocument\Exceptions\UnknownHashAlgorithmException
     */
    public function testSignatureUnknownHashAlgorithm(){
        $adoc = new ADoc();
        $adoc->setSignatureAlgorithm('ddasssaa');
    }
}