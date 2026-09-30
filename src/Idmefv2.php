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

namespace GlpiPlugin\Idmefv2;

use InvalidArgumentException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use LogicException;
use Plugin;
use RuntimeException;
use stdClass;

use function Safe\file_get_contents;
use function Safe\json_decode;

class Idmefv2
{
    /**
     * @param array $message an array representation of a IDMEFv2 message (json_decoded)
     */
    public function getVersion(stdClass $message): string
    {
        $version = $message->Version ?? 'latest';
        // Check that $version contains only digits, dots and letters D and V
        $allowed_chars = '0123456789.DV';
        $cleaned = str_replace(str_split($allowed_chars), '', $version);
        if (strlen($cleaned) !== 0) {
            throw new InvalidArgumentException('Invalid IDMEFv2 version identifier');
        }

        return $version;
    }

    /**
     * get IDMEFv2 specification file by given version
     * @param string $version
     */
    public function getSpecificationFilePath(string $version = 'latest'): string
    {
        $file = 'IDMEFv' . $version . '.schema';
        $path = Plugin::getPhpDir('idmefv2') . '/spec/' . $file;

        return $path;
    }

    public function isIdmefv2Compliant(stdClass $message): bool
    {
        try {
            $version = $this->getVersion($message);
        } catch (LogicException $e) {
            return false;
        }
        $path = $this->getSpecificationFilePath($version);
        try {
            $schema_string = file_get_contents($path);
            $schema = json_decode($schema_string);
        } catch (RuntimeException $e) {
            return false;
        }

        $validator = new Validator();
        $validator->validate($message, $schema, Constraint::CHECK_MODE_NORMAL);
        $validator->getErrors();
        return $validator->isValid();
    }
}