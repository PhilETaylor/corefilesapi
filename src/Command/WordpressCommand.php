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

use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class WordpressCommand extends ContainerAwareCommand
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
     * CorefilesCommand constructor.
     *
     * @param null|string $name
     * @param Client $guzzle
     */
    public function __construct(?string $name = null, Client $guzzle)
    {
        $this->guzzle = $guzzle;

        $root = realpath(__DIR__ . '/../../');

        if (!file_exists($root . '/var/tmp/')) {
            mkdir($root . '/var/tmp/');
        }

        if (!file_exists($root . '/public/downloads/Wordpress')) {
            mkdir($root . '/public/downloads/Wordpress');
        }

        if (!file_exists($root . '/public/downloads/Wordpress/Releases')) {
            mkdir($root . '/public/downloads/Wordpress/Releases');
        }
        if (!file_exists($root . '/public/downloads/Wordpress/Hashes')) {
            mkdir($root . '/public/downloads/Wordpress/Hashes');
        }
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('wordpress:download:all')
            ->setDescription('Download and hash all wordpress versions')
            ->setHelp('');
    }

    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $html = $this->guzzle->get('https://wordpress.org/download/releases/')->getBody()->getContents();

        $crawler = new Crawler($html, '', 'https://wordpress.org/');

        $links = $crawler->filter('a')->links();

        $toDownload = [];

        foreach ($links as $link) {

            if ('zip' !== $link->getNode()->nodeValue) {
                continue;
            }

            $downloadLink = $link->getNode()->getAttribute('href');

            if (!$downloadLink || preg_match('/-mu-/ism', $downloadLink)) {
                continue;
            }

            $hashFile = $this->generateLocalName($downloadLink);

            if (!file_exists($hashFile)) {
                $this->doHash($hashFile, $downloadLink);
            }
        }
    }

    /**
     * @param string $downloadLink
     * @return string
     */
    private function generateLocalName(string $downloadLink)
    {
        $downloadLink = str_replace([
            '.zip',
            'https://wordpress.org/wordpress-',
        ],
            '',
            $downloadLink);

        $root = realpath(__DIR__ . '/../../');

        return $root . '/public/downloads/Wordpress/Hashes/' . $downloadLink . '.txt';
    }

    private function doHash(string $hashFile, string $downloadLink)
    {
        $localZip = $this->generateFileName($downloadLink);
        file_put_contents($localZip, file_get_contents($downloadLink));
        chown($localZip, 'www-data');
        chgrp($localZip, 'www-data');
        sleep(3);

        $this->output->writeln('__');
        $this->output->writeln('<info>Im going to generate md5 hashes based on file ' . $localZip . '</info>');

        $filesystem = new Filesystem(new ZipArchiveAdapter($localZip));
        $contents = $filesystem->listContents('.', true);

        $this->output->writeln(sprintf('There are %s files to calculate hashes for...', count($contents)));

        $this->output->writeln(sprintf('Im going to save the md5 hashs file as %s', $hashFile));

        // create a new progress bar (50 units)
        $progress = new ProgressBar($this->output, count($contents));

        $str = '';

        foreach ($contents as $object) {

            if ('file' != $object['type']) {
                continue;
            }
            $filecontents = $filesystem->read($object['path']);
            $str .= preg_replace('/^wordpress\//', '', $object['path']) . "\t" . hash('md5', $filecontents) . "\n";

            $progress->advance();
        }
        $progress->finish();

        file_put_contents($hashFile, $str);
        chown($hashFile, 'www-data');
        chgrp($hashFile, 'www-data');
        sleep(3);
    }

    /**
     * @param string $downloadLink
     * @return string
     */
    private function generateFileName(string $downloadLink)
    {
        $downloadLink = str_replace([
            'https://wordpress.org/',
        ],
            '',
            $downloadLink);

         $root = realpath(__DIR__ . '/../../');

        return $root . '/public/downloads/Wordpress/Releases/' . $downloadLink;
    }
}
