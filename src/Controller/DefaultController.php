<?php

namespace App\Controller;

use Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;

/**
 * Class DefaultController
 * @package App\Controller
 */
class DefaultController extends Controller
{

    const PLATFORM_JOOMLA = 'joomla';
    const PLATFORM_WORDPRESS = 'wordpress';
    const FORMAT_RAW = 'raw';
    const FORMAT_PRETTY = 'pretty';
    const FORMAT_PRE = 'pre';
    const FORMAT_FOLDER = 'folder';
    const FORMAT_MD5 = 'md5';
    const FORMAT_SHA1 = 'sha1';
    const FORMAT_STAT = 'stat';
    const FOLDER = 'FOLDER';
    const FILE = 'FILE';
    const IMAGE = 'IMAGE';

    /**
     * @var string
     */
    private $type;

    /**
     * @Route("/", name="app_homepage")
     * @return Response
     * @throws Exception
     */
    public function indexAction()
    {
        return $this->render('home.html.twig');
    }

    /**
     * @Route("/hashes/{platform}/{version}/{format}", requirements={"platform"="wordpress|joomla"}, defaults={"format"="gz"})
     * @Cache(expires="+1 Year", public=true)
     *
     * @param $platform
     * @param $version
     * @param string $format
     *
     * @return RedirectResponse|Response
     */
    public function hashesAction($platform, $version, $format = 'gz')
    {
        if (preg_match('/\.\./', $platform) || preg_match('/\.\./', $version)) {
            return $this->redirectToRoute('app_homepage');
        }

        $hashFile = realpath(sprintf('./downloads/%s/Hashes/', ucwords(strtolower($platform)))) . '/' . $version . '.txt';


        if (!file_exists($hashFile)) {
            return $this->redirectToRoute('app_homepage');
        }

        if ($format == 'gz') {
            return new Response(gzdeflate(file_get_contents($hashFile)), 200, [
                'Content-Type' => "application/x-gzip"
            ]);
        } else {
            return new Response(file_get_contents($hashFile), 200, [
                'Content-Type' => "text/plain"
            ]);
        }
    }

    /**
     * @Route("/files")
     * @return JsonResponse
     */
    public function filesAction()
    {

        $finder = new Finder();
        $finder->files()->in('./downloads');

        foreach ($finder as $file) {
            $links[$file->getRelativePath()][] = $file->getRelativePathname();
        }

        return new JsonResponse($links ?? []);
    }

    /**
     * @Route("/files/info")
     * @Cache(expires="+1 Year", public=true)
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    public function filesinfoAction()
    {
        $cache = new FilesystemCache();

        if (count($_GET)) {
            $cache->clear();
        }

        if (!$links = $cache->get('filesinfoAction')) {
            $finder = new Finder();
            $finder->files()->in('./downloads');

            foreach ($finder as $file) {
                if (!preg_match('/Releases/', $file->getRealPath())) {
                    continue;
                }
                $links[$file->getRelativePath()][] = [
                    'filename' => str_replace([
                        'Wordpress/Releases/',
                        'Joomla/Releases/',
                    ], '', $file->getRelativePathname()),
                    'md5' => hash('md5', file_get_contents($file->getRealPath())),
                    'sha1' => hash('sha1', file_get_contents($file->getRealPath())),
                    'size' => filesize($file->getRealPath()),
                ];
            }
            $cache->set('filesinfoAction', $links, 3600);
        }

        return new JsonResponse($links ?? []);
    }


    /**
     * @Route("/{format}/{platform}/{version}/{file}", requirements={"platform"="joomla|wordpress","file"=".+"})
     * @Cache(expires="+1 Year", public=true)
     * @param Request $request
     * @param string $version
     * @param string $platform
     * @param string $file
     * @param string $format
     * @return Response
     * @throws Exception
     */
    public function mainAction(Request $request, string $version = '', string $platform = '', $file = '', $format = 'raw2')
    {
        if (preg_match('/\.\./', $version)) {
            throw new Exception('Invalid version');
        }

        switch (strtolower($platform)) {
            case self::PLATFORM_JOOMLA:
                $fileToRead = sprintf('Joomla_%s-Stable-Full_Package.zip', $version);
                $zipFile = realpath('./downloads/Joomla/Releases/') . '/' . $fileToRead;
                break;
            case self::PLATFORM_WORDPRESS:
                $fileToRead = sprintf('wordpress-%s.zip', $version);
                $zipFile = realpath('./downloads/Wordpress/Releases/') . '/' . $fileToRead;
                break;
            default:
                throw new Exception('Invalid Platform');
                break;
        }

        if ("" === $file || preg_match('#/$#', $file)) {
            $this->type = self::FOLDER;
            $format = self::FORMAT_FOLDER;
            $folder = $file;
        } else {
            $this->type = self::FILE;

            $path_parts = pathinfo($file);

            if (!array_key_exists('extension', $path_parts)) {
                $folder = $file;
                $lang = null;
            } else {
                $folder = false;
                $lang = $path_parts['extension'];
                switch ($lang) {
                    case 'gif':
                        $this->type = self::IMAGE;
                        $contentType = 'image/gif';
                        break;
                    case 'png':
                        $this->type = self::IMAGE;
                        $contentType = 'image/png';
                        break;
                    case 'jpg':
                        $this->type = self::IMAGE;
                        $contentType = 'image/jpeg';
                        break;
                    case 'svg':
                        $this->type = self::IMAGE;
                        break;
                }
            }
        }

        if (!file_exists($zipFile)) throw new Exception('Invalid Version To File Mapping: ');

        $zip = new ZipArchive;
        if (!$zip->open($zipFile)) throw new Exception('Could not open zip file for this version or version invalid');

        if (FALSE !== $folder) {
            $urls = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($platform == self::PLATFORM_WORDPRESS) {
                    if (preg_match('#^wordpress/' . $folder . '#', $name)) {
                        $urls[] = preg_replace('/^wordpress\//ism', '', $name);
                    }
                } else {
                    if (preg_match('#^' . $folder . '#', $name)) {
                        $urls[] = $name;
                    }
                }
            }
        } else {
            if ($platform == self::PLATFORM_WORDPRESS) {
                $file = 'wordpress/' . $file;
                $txt = $zip->getFromName($file);
            } else {
                $txt = $zip->getFromName($file);
            }
        }

        if ($format == 'auto' && $this->type == self::FOLDER) {
            $format = self::FORMAT_FOLDER;
        } else if ($format == 'auto' && $this->type == self::FILE) {
            $format = self::FORMAT_PRE;
        }

        if ($this->type === self::IMAGE) {
            $response = new Response();
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($file));
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Type', $contentType);
            $response->setContent($txt);

            return $response;

        }

        switch ($format) {
            case self::FORMAT_RAW:
                $template = 'raw.html.twig';
                break;
            case self::FORMAT_FOLDER:
                $template = 'folder.html.twig';
                break;
            case self::FORMAT_PRETTY:
                $txt = htmlentities($txt);
                $template = 'pretty.html.twig';
                break;
            case self::FORMAT_PRE:
                $txt = htmlentities($txt);
                $template = 'pre.html.twig';
                break;
            case self::FORMAT_FOLDER:
                $template = 'folder.html.twig';
                break;
            case self::FORMAT_SHA1:
                $hash = hash('sha1', $txt);
                $template = 'hash.html.twig';
                break;
            case self::FORMAT_MD5:
                $hash = hash('md5', $txt);
                $template = 'hash.html.twig';
                break;
            case self::FORMAT_STAT:
                if ($this->type == self::FOLDER) {
                    return new JsonResponse([$urls]);
                } else {
                    return new JsonResponse([
                        'parent' => $fileToRead,
                        'md5' => hash('md5', $txt),
                        'sha1' => hash('sha1', $txt),
                        'base64' => base64_encode($txt),
                        'strlen' => strlen($txt),
                    ]);
                }

                break;
            default:
                throw new Exception('Invalid format');
                break;
        }

        if ('pretty' === $format && $lang == 'js') {
            $lang = 'javascript';
        }

        return $this->render($template,
            [
                'file' => $fileToRead,
                'version' => $version,
                'format' => $format,
                'platform' => $platform ?? '',
                'txt' => $txt ?? '',
                'hash' => $hash ?? '',
                'lang' => $lang ?? '',
                'urls' => $urls ?? [],
            ]);
    }
}