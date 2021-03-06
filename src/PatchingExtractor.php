<?php

/**
 * This file is part of contao-community-alliance/build-system-tool-author-validation.
 *
 * (c) Contao Community Alliance <https://c-c-a.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/build-system-tool-author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  Contao Community Alliance <https://c-c-a.org>
 * @link       https://github.com/contao-community-alliance/build-system-tool-author-validation
 * @license    https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation;

/**
 * Interface for an author information extractor that can patch it's input.
 */
interface PatchingExtractor extends AuthorExtractor
{
    /**
     * Update author list in the storage with the given authors.
     *
     * @param string $path    A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @param string $authors The author list that shall be used in the resulting buffer (optional, if empty the buffer
     *                        is unchanged).
     *
     * @return string The new storage content with the updated author list.
     */
    public function getBuffer($path, $authors = null);
}
