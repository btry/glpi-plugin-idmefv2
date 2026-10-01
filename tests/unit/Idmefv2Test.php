<?php

/**
 *  -------------------------------------------------------------------------
 *  IDMEFv2 plugin for GLPI
 *
 * @copyright Copyright (C) 2024-2025 Teclib' and contributors.
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPLv3+
 * @link      https://idmefv2.ovh
 * @link      https://github.com/idmefv2
 *
 *  -------------------------------------------------------------------------
 *
 *  LICENSE
 *
 *  This file is part of IDMEFv2 plugin for GLPI.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *  -------------------------------------------------------------------------
 */

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
