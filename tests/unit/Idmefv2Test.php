<?php

namespace tests\units\GlpiPlugin\Idmefv2;

use GlpiPlugin\Idmefv2\Idmefv2;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin;
use Ramsey\Uuid\Uuid;
use Safe\DateTime;
use stdClass;

#[CoversClass(Idmefv2::class)]
final class Idmefv2Test extends TestCase
{
    public function testGetVersionAcceptsValidVersionIdentifier(): void
    {
        $message = new StdClass();
        $message->Version = '2.D.V08';

        $this->assertSame('2.D.V08', (new Idmefv2())->getVersion($message));
    }

    public function testGetVersionRejectsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IDMEFv2 version identifier');
        $message = new StdClass();
        $message->Version = '2.D.V08-beta';
        (new Idmefv2())->getVersion($message);
    }

    public function testGetSpecificationFilePathReturnsExpectedSchemaFile(): void
    {
        $sut = new Idmefv2();

        $this->assertSame(
            Plugin::getPhpDir('idmefv2') . '/spec/IDMEFv2.D.V08.schema',
            $sut->getSpecificationFilePath('2.D.V08')
        );
    }

    public function testIsIdmefv2CompliantReturnsTrueForValidMessage(): void
    {
        $analyzer = new stdClass();
        $analyzer->IP = '10.0.0.5';
        $analyzer->Name = 'test-analyzer';
        $message = new StdClass();
        $message->Version = '2.D.V08';
        $message->ID = Uuid::uuid4()->toString();
        $message->CreateTime = new DateTime()->format(DateTime::ATOM);
        $message->Analyzer = $analyzer;
        $this->assertTrue((new Idmefv2())->isIdmefv2Compliant($message));
    }

    public function testIsIdmefv2CompliantReturnsFalseForInvalidVersionInMessage(): void
    {
        $analyzer = new stdClass();
        $analyzer->IP = '10.0.0.5';
        $analyzer->Name = 'test-analyzer';
        $message = new StdClass();
        $message->Version = 'INVALID';
        $message->ID = Uuid::uuid4()->toString();
        $message->CreateTime = new DateTime()->format(DateTime::ATOM);
        $message->Analyzer = $analyzer;

        $this->assertFalse((new Idmefv2())->isIdmefv2Compliant($message));
    }
    public function testIsIdmefv2CompliantReturnsFalseForInvalidMessage(): void
    {
        $analyzer = new stdClass();
        $analyzer->IP = '10.0.0.5';
        $analyzer->Name = 'test-analyzer';
        $message = new StdClass();
        $message->Version = '2.D.V08';
        $message->ID = Uuid::uuid4()->toString();
        $message->Analyzer = $analyzer;

        $this->assertFalse((new Idmefv2())->isIdmefv2Compliant($message));
    }
}
