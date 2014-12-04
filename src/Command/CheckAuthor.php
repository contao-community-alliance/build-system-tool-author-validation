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
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  Contao Community Alliance <https://c-c-a.org>
 * @link       https://github.com/contao-community-alliance/build-system-tool-author-validation
 * @license    https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Command;

use ContaoCommunityAlliance\BuildSystem\Repository\GitRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class to check the mentioned authors.
 *
 * @package ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Command
 */
class CheckAuthor extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('ccabs:tools:check-author')
            ->setDescription('Check that all authors are mentioned in each file.')
            ->addArgument(
                'dir',
                InputArgument::OPTIONAL,
                'The directory to start searching, must be a git repository or a subdir in a git repository.',
                '.'
            );
    }

    /**
     * Determine the git root, starting from arbitrary directory.
     *
     * @param string $path The start path.
     *
     * @return string The git root path.
     *
     * @throws \RuntimeException If the git root could not determined.
     */
    private function determineGitRoot($path)
    {
        // @codingStandardsIgnoreStart
        while (strlen($path) > 1) {
            // @codingStandardsIgnoreEnd
            if (is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
                return $path;
            }

            $path = dirname($path);
        }

        throw new \RuntimeException('Could not determine git root, starting from ' . func_get_arg(0));
    }

    /**
     * Find PHP files, read the authors and validate against the git log of each file.
     *
     * @param string          $dir    The directory to search files in.
     * @param GitRepository   $git    The git repository.
     * @param OutputInterface $output The output.
     *
     * @return bool
     */
    private function validatePhpAuthors($dir, GitRepository $git, OutputInterface $output)
    {
        $finder = new Finder();

        $finder->in($dir)->notPath('/vendor/')->files()->name('*.php');

        $invalidates = false;

        foreach ($finder as $file) {
            /** @var \SplFileInfo $file */

            $mentionedAuthors = file($file);
            $mentionedAuthors = preg_filter('~.*@author\s+(.*)\s*~', '$1', $mentionedAuthors);
            usort($mentionedAuthors, 'strcasecmp');
            $mentionedAuthors = array_unique($mentionedAuthors);

            $authors = $git->log()->format('%aN <%ae>')->follow()->execute($file->getPathname());
            $authors = preg_split('~[\r\n]+~', $authors);
            usort($authors, 'strcasecmp');
            $authors = array_unique($authors);

            $invalidates = !$this->validateAuthors($file->getPathname(), $mentionedAuthors, $authors, $output)
                           || $invalidates;
        }

        return !$invalidates;
    }

    /**
     * Read the composer.json, if exist and validate the authors in the file against the git log.
     *
     * @param string          $dir    The directory to search for the composer.json.
     * @param GitRepository   $git    The git repository.
     * @param OutputInterface $output The output.
     *
     * @return bool
     */
    private function validateComposerAuthors($dir, GitRepository $git, OutputInterface $output)
    {
        $pathname = $dir . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($pathname)) {
            return false;
        }

        $composerJson = file_get_contents($pathname);
        $composerJson = (array) json_decode($composerJson, true);

        if (isset($composerJson['authors']) && is_array($composerJson['authors'])) {
            $mentionedAuthors = array_map(
                function ($author) {
                    if (isset($author['email'])) {
                        return sprintf(
                            '%s <%s>',
                            $author['name'],
                            $author['email']
                        );
                    }

                    return $author['name'];
                },
                $composerJson['authors']
            );
        } else {
            $mentionedAuthors = array();
        }

        $authors = $git->log()->format('%aN <%ae>')->execute();
        $authors = preg_split('~[\r\n]+~', $authors);
        usort($authors, 'strcasecmp');
        $authors = array_unique($authors);

        return $this->validateAuthors($pathname, $mentionedAuthors, $authors, $output);
    }

    /**
     * Read the bower.json, if exist and validate the authors in the file against the git log.
     *
     * @param string          $dir    The directory to search for the bower.json.
     * @param GitRepository   $git    The git repository.
     * @param OutputInterface $output The output.
     *
     * @return bool
     */
    private function validateBowerAuthors($dir, GitRepository $git, OutputInterface $output)
    {
        $pathname = $dir . DIRECTORY_SEPARATOR . 'bower.json';

        if (!is_file($pathname)) {
            return false;
        }

        $bowerJson = file_get_contents($pathname);
        $bowerJson = (array) json_decode($bowerJson, true);

        if (isset($bowerJson['authors']) && is_array($bowerJson['authors'])) {
            $mentionedAuthors = array_map(
                function ($author) {
                    if (is_string($author)) {
                        return $author;
                    }

                    if (isset($author['email'])) {
                        return sprintf(
                            '%s <%s>',
                            $author['name'],
                            $author['email']
                        );
                    }

                    return $author['name'];
                },
                $bowerJson['authors']
            );
        } else {
            $mentionedAuthors = array();
        }

        $authors = $git->log()->format('%aN <%ae>')->execute();
        $authors = preg_split('~[\r\n]+~', $authors);
        usort($authors, 'strcasecmp');
        $authors = array_unique($authors);

        return $this->validateAuthors($pathname, $mentionedAuthors, $authors, $output);
    }

    /**
     * Read the packages.json, if exist and validate the authors in the file against the git log.
     *
     * @param string          $dir    The directory to search for the packages.json.
     * @param GitRepository   $git    The git repository.
     * @param OutputInterface $output The output.
     *
     * @return bool
     */
    private function validateNodeAuthors($dir, GitRepository $git, OutputInterface $output)
    {
        $pathname = $dir . DIRECTORY_SEPARATOR . 'packages.json';

        if (!is_file($pathname)) {
            return false;
        }

        $packagesJson = file_get_contents($pathname);
        $packagesJson = (array) json_decode($packagesJson, true);

        $mentionedAuthors = array();

        if (isset($packagesJson['author'])) {
            if (isset($packagesJson['author']['email'])) {
                $mentionedAuthors[] = sprintf(
                    '%s <%s>',
                    $packagesJson['author']['name'],
                    $packagesJson['author']['email']
                );
            } else {
                $mentionedAuthors[] = $packagesJson['author']['name'];
            }
        }

        if (isset($packagesJson['contributors'])) {
            foreach ($packagesJson['contributors'] as $contributor) {
                if (isset($contributor['email'])) {
                    $mentionedAuthors[] = sprintf(
                        '%s <%s>',
                        $contributor['name'],
                        $contributor['email']
                    );
                } else {
                    $mentionedAuthors[] = $contributor['name'];
                }
            }
        }

        $authors = $git->log()->format('%aN <%ae>')->execute();
        $authors = preg_split('~[\r\n]+~', $authors);
        usort($authors, 'strcasecmp');
        $authors = array_unique($authors);

        return $this->validateAuthors($pathname, $mentionedAuthors, $authors, $output);
    }

    /**
     * Validate mentioned and real authors against each other.
     *
     * @param string          $pathname         The source file pathname.
     * @param array           $mentionedAuthors List of mentioned authors.
     * @param array           $authors          List of real authors, read from git.
     * @param OutputInterface $output           The output to write the error messages to.
     *
     * @return bool
     */
    private function validateAuthors($pathname, array $mentionedAuthors, array $authors, OutputInterface $output)
    {
        $validates       = true;
        $wasteMentions   = array_diff($mentionedAuthors, $authors);
        $missingMentions = array_diff($authors, $mentionedAuthors);

        if (count($wasteMentions)) {
            $output->writeln(
                sprintf(
                    'The file <info>%s</info> mention authors that are unnecessary: <comment>%s</comment>',
                    $pathname,
                    implode(PHP_EOL, $wasteMentions)
                )
            );
            $validates = false;
        }

        if (count($missingMentions)) {
            $output->writeln(
                sprintf(
                    'The file <info>%s</info> miss mention of authors: <comment>%s</comment>',
                    $pathname,
                    implode(PHP_EOL, $missingMentions)
                )
            );
            $validates = false;
        }

        return $validates;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir     = realpath($input->getArgument('dir'));
        $gitRoot = $this->determineGitRoot($dir);
        $git     = new GitRepository($gitRoot);
        $error   = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $git->getConfig()->setLogger(
                new ConsoleLogger($output)
            );
        }

        $failed = false;
        $failed = !$this->validatePhpAuthors($dir, $git, $error) || $failed;
        $failed = !$this->validateComposerAuthors($gitRoot, $git, $error) || $failed;
        $failed = !$this->validateBowerAuthors($gitRoot, $git, $error) || $failed;
        $failed = !$this->validateNodeAuthors($gitRoot, $git, $error) || $failed;

        return $failed ? 1 : 0;
    }
}
