<?php

/**
 * @copyright  Copyright (C) 2017, 2018 Blue Flame Digital Solutions Limited / Phil Taylor. All rights reserved.
 * @author     Phil Taylor <phil@phil-taylor.com>
 *
 * @see        https://github.com/PhilETaylor/maintain.myjoomla.com
 *
 * @license    Commercial License - Not For Distribution.
 */

namespace App\Command;

use ErrorException;
use GuzzleHttp\Client;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JoomlaCommand extends ContainerAwareCommand
{

    /**
     * @var Client
     */
    private $guzzle;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var
     */
    private $root;

    /**
     * CorefilesCommand constructor.
     *
     * @param null|string $name
     * @param Client $guzzle
     */
    public function __construct(?string $name = null, Client $guzzle)
    {
        $this->guzzle = $guzzle;

        $root = $this->root = realpath(__DIR__ . '/../../');

        if (!file_exists($root . '/var/tmp/')) {
            mkdir($root . '/var/tmp/');
        }

        if (!file_exists($root . '/public/downloads/Joomla')) {
            mkdir($root . '/public/downloads/Joomla');
        }

        if (!file_exists($root . '/public/downloads/Joomla/Releases')) {
            mkdir($root . '/public/downloads/Joomla/Releases');
        }
        if (!file_exists($root . '/public/downloads/Joomla/Hashes')) {
            mkdir($root . '/public/downloads/Joomla/Hashes');
        }
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('joomla:download:all')
            ->setDescription('Run when a new Joomla release is available, automate everything')
            ->setHelp('');
    }


    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws FileNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $client = new Client();
        $json = $client->get('https://downloads.joomla.org/api/v1/releases/cms/')->getBody()->getContents();
        $json = json_decode($json);

        foreach ($json->releases as $release) {

            if (in_array($release->branch, ['Install from Web', 'Weblinks'])) {
                continue;
            }

            $url = 'https://downloads.joomla.org/cms/%s/%s/Joomla_%s-Stable-Full_Package.zip?format=zip';

            $series = strtolower(str_replace(['! ', '.'], '', $release->branch));
            $versionBarred = strtolower(str_replace('.', '-', $release->version));

            $url = sprintf($url,
                $series,
                $versionBarred,
                $versionBarred
            );

            $fileName = sprintf('Joomla_%s-Stable-Full_Package.zip', $release->version);


            $urls = [
                'downloadUrl' => $url,
                'localfile' => $this->root . '/public/downloads/Joomla/Releases/' . $fileName,
                'hashfile' => $this->root . '/public/downloads/Joomla/Hashes/' . $release->version . '.txt'
            ];

            // Exceptions - doh!
            if ($release->version === '3.1.3'
                || $release->version === '3.1.2'
                || $release->version === '2.5.12'
                || $release->version === '1.0.5'
                || $release->version === '1.0.4'
                || $release->version === '1.0.3'
                || $release->version === '1.0.2'
                || $release->version === '1.0.1'
                || $release->version === '1.0.0'
            ) {
                continue;
            }

            if ($release->version === '3.8.1') {
                $urls['downloadUrl'] = 'https://downloads.joomla.org/extensions/joomla3/3-8-1/joomla_3-8-1-stable-full_package-zip?format=zip';
            }
            if ($release->version === '3.7.5') {
                $urls['downloadUrl'] = 'https://downloads.joomla.org/cms/joomla3/3-7-5/Joomla_3-7.5-Stable-Full_Package.zip?format=zip';
            }
            if ($release->version === '3.7.4') {
                $urls['downloadUrl'] = 'https://downloads.joomla.org/cms/joomla3/3-7-4/Joomla_3-7.4-Stable-Full_Package.zip?format=zip';
            }
            if ($release->version === '3.7.3') {
                $urls['downloadUrl'] = 'https://downloads.joomla.org/cms/joomla3/3-7-3/Joomla_3.7.3-Stable-Full_Package.zip?format=zip';
            }
            if ($release->version === '1.5.0') {
                $urls['downloadUrl'] = 'https://downloads.joomla.org/cms/joomla15/1-5-0/joomla-1-5-0-zip?format=zip';
            }

            if (!file_exists($urls['hashfile'])) {
                $this->output->writeln('<info>Im going to download file ' . $urls['downloadUrl'] . '</info>');

                try {

                    $data = file_get_contents($urls['downloadUrl']);

                    if (!$data) {
                        throw new ErrorException();
                    }

                    file_put_contents($urls['localfile'], $data);

                    chown($urls['localfile'], 'www-data');
                    chgrp($urls['localfile'], 'www-data');

                } catch (ErrorException $exception) {
                    $urls['downloadUrl'] = str_replace(
                        [
                            'Joomla',
                            'Stable',
                            'Full',
                            'Package.zip',
                        ],
                        [
                            'joomla',
                            'stable',
                            'full',
                            'package-zip',
                        ],
                        $urls['downloadUrl']);

                    $this->output->writeln('<info>Grrr.... Im going to try from another url: ' . $urls['downloadUrl'] . '</info>');

                    $data = file_get_contents($urls['downloadUrl']);
                    if (!$data) {
                        throw new ErrorException();
                    }
                    file_put_contents($urls['localfile'], $data);
                    chown($urls['localfile'], 'www-data');
                    chgrp($urls['localfile'], 'www-data');

                }

                $this->output->writeln('<info>Im going to generate md5 hashes based on file ' . $urls['localfile'] . '</info>');

                $filesystem = new Filesystem(new ZipArchiveAdapter($urls['localfile']));
                $contents = $filesystem->listContents('.', true);

                $this->output->writeln(sprintf('There are %s files to calculate hashes for...', count($contents)));

                $this->output->writeln(sprintf('Im going to save the md5 hashs file as %s', $urls['hashfile']));

                // create a new progress bar (50 units)
                $progress = new ProgressBar($this->output, count($contents));

                $str = '';

                foreach ($contents as $object) {
                    if ('file' != $object['type']) {
                        continue;
                    }
                    $filecontents = $filesystem->read($object['path']);
                    $str .= $object['path'] . "\t" . hash('md5', $filecontents) . "\n";
                    $progress->advance();
                }
                $progress->finish();

                file_put_contents($urls['hashfile'], $str);

                chown($urls['hashfile'], 'www-data');
                chgrp($urls['hashfile'], 'www-data');

                $this->output->writeln('');
                $this->output->writeln('<info>Saved md5s to file ' . $urls['hashfile'] . '</info>');
            }
        }
    }
}
