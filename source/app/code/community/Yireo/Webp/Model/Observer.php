<?php
/**
 * Webp plugin for Magento
 *
 * @package     Yireo_Webp
 * @author      Yireo (https://www.yireo.com/)
 * @copyright   Copyright 2015 Yireo (https://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

/**
 * Class Yireo_Webp_Model_Observer
 */
class Yireo_Webp_Model_Observer
{
    /**
     * @var Yireo_Webp_Helper_Data
     */
    protected $helper;

    /**
     * @var Yireo_Webp_Helper_File
     */
    protected $fileHelper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->helper = Mage::helper('webp');
        $this->fileHelper = Mage::helper('webp/file');
    }

    /**
     * @param Mage_Core_Block_Abstract $block
     *
     * @return bool
     */
    protected function isAllowedBlock($block)
    {
        $allowedBlocks = array('root');

        if (in_array($block->getNameInLayout(), $allowedBlocks)) {
            return true;
        }

        return false;
    }

    /**
     * Listen to the event core_block_abstract_to_html_after
     *
     * @parameter Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function coreBlockAbstractToHtmlAfter($observer)
    {
        if ($this->helper->canUse() == false) {
            return $this;
        }

        $transport = $observer->getEvent()->getTransport();
        $block = $observer->getEvent()->getBlock();

        /* if ($this->isAllowedBlock($block) == false) {
            return $this;
        } */

        $html = $transport->getHtml();

        if (preg_match_all('/\ (src|srcset)=\"([^\"]+)\.(png|jpg|jpeg)/i', $html, $matches) == false) {
            return $this;
        }
        //Mage::log(__METHOD__);
        //$imageList = array();

        //Mage::log($matches[0]);

        foreach ($matches[0] as $index => $match) {
            switch($matches[1][$index]) {
                case 'src':
                    // Convert the URL to a valid path
                    $imageUrl = $matches[2][$index] . '.' . $matches[3][$index];
                    //Mage::log($imageUrl);
                    $webpUrl = $this->convertImageUrlToWebp($imageUrl);

                    if (empty($webpUrl)) {
                        //Mage::log('cant convert '.$imageUrl.' to webp');
                        continue;
                    }

                    // Replace the img tag in the HTML
                    $htmlTag = $matches[0][$index];
                    //$newHtmlTag = str_replace('src="' . $imageUrl, ' src="' . Mage::getBaseUrl("skin") . 'frontend/default/default/images/webp/placeholder.png" data-img="' . md5($imageUrl), $htmlTag);
                    $newHtmlTag = str_replace('src="' . $imageUrl, ' src="' . $webpUrl .'" data-originalSrc="' . $imageUrl, $htmlTag);
                    $html = str_replace($htmlTag, $newHtmlTag, $html);
                    

                    // Add the images to the return-array
                    //$imageList[md5($imageUrl)] = array('orig' => $imageUrl, 'webp' => $webpUrl);
                break;
                case 'srcset':
                    //Mage::log("\n - - NEW SRCSET DETECTED ".$match);
                    $imageUrls = $matches[2][$index] . '.' . $matches[3][$index];
                    //Mage::log( $imageUrls );
                    if (preg_match_all('/[^"\'=\s]+\.(jpe?g|png)/mU', $imageUrls, $matches2) == false) {
                        //Mage::log( 'no match' );
                        continue;
                    }
                    //Mage::log('--- $matches2 ---');
                    //Mage::log($matches2);
                    $htmlTag = $matches[0][$index];
                    //Mage::log('--- $htmlTag ---');
                    //Mage::log($htmlTag);
                    foreach($matches2[0] as $index2 => $match2) {
                        // Convert the URL to a valid path
                        $imageUrl2 = $match2;
                        //Mage::log('-- $imageUrl2');
                        //Mage::log($imageUrl2);
                        $webpUrl2 = $this->convertImageUrlToWebp($imageUrl2);
                        if (empty($webpUrl2)) {
                            //Mage::log('cant convert '.$imageUrl2.' to webp');
                            continue;
                        }
                        //Mage::log('\n$webpUrl = '.$webpUrl2);

                        $newHtmlTag = str_replace($imageUrl2, $webpUrl2, $htmlTag);
                        //Mage::log('--- $newHtmlTag ---');
                        //Mage::log($newHtmlTag);
                        $html = str_replace($htmlTag, $newHtmlTag, $html);
                        $htmlTag = $newHtmlTag;
                    }

                    //$html = str_replace($htmlTag, $newHtmlTag, $html);
                    

                    //Mage::log($matches[3][$index]);
                    //Mage::log("\n\n-------\n\n");
                    // Convert the URL to a valid path
                    // $imageUrl = $matches[2][$index] . '.' . $matches[3][$index];
                    // $webpUrl = $this->convertImageUrlToWebp($imageUrl);

                    // if (empty($webpUrl)) {
                    //     //Mage::log('cant convert '.$imageUrl.' to webp');
                    //     continue;
                    // }

                    // // Replace the img tag in the HTML
                    // $htmlTag = $matches[0][$index];
                    // //$newHtmlTag = str_replace('src="' . $imageUrl, ' src="' . Mage::getBaseUrl("skin") . 'frontend/default/default/images/webp/placeholder.png" data-img="' . md5($imageUrl), $htmlTag);
                    // $newHtmlTag = str_replace('srcset="' . $imageUrl, ' srcset="' . $webpUrl .'" data-originalSrcSet="' . $imageUrl, $htmlTag);
                    // $html = str_replace($htmlTag, $newHtmlTag, $html);
                break;
            }
        }

        // Add a JavaScript-list to the HTML-document
        // if (empty($imageList)) {
        //     return $this;
        // }

        /*$newHtml = $this->getScriptHtmlLines($imageList);

        if ($block->getNameInLayout() == 'root') {
            $newHtml[] = '<script type="text/javascript" src="' . Mage::getBaseUrl('js') . 'webp/jquery.detect.js"></script>';
        }

        $html = $this->addScriptToBody($html, $newHtml);*/
        
        // link preload product image
        /* $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('(//img[@id="image-main"])[1]');
        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $src = sprintf('<link rel="preload" as="image" href="%s" />', $node->getAttribute('src'));
            $html = str_replace('</head>', $src . '</head>', $html);
        }  */
        
        
        
        $transport->setHtml($html);

        return $this;
    }

    /**
     * @param $imageUrl
     *
     * @return bool|mixed
     */
    protected function convertImageUrlToWebp($imageUrl)
    {
        $imagePath = $this->getImagePathFromUrl($imageUrl);
        //Mage::log(__METHOD__);
        if (empty($imagePath)) {
            Mage::log('empty imagepath '.$imagePath);
            Mage::log('imageurl '.$imageUrl);
            
            return false;
        }

        if ($this->fileHelper->exists($imagePath) == false) {
            if (strstr($imagePath, 'pergamina')) {
                Mage::log('file exist '.$imagePath);
                return false;
            }
            //Mage::log('file exist '.$imagePath);
            return false;
        }

        // Construct the new WebP image-name
        $webpPath = $this->helper->convertToWebp($imagePath);

        if (empty($webpPath)) {
            Mage::log('not converted '.$imagePath.' webpPath '.$webpPath);
            return false;
        }

        if ($this->fileHelper->exists($webpPath) == false) {
            Mage::log('webpPath not exist '.$webpPath);
            return false;
        }

        // Convert the path back to a valid URL
        $webpUrl = $this->getImageUrlFromPath($webpPath);

        if (empty($webpUrl)) {
            //Mage::log('getImageUrlFromPath empty '.$webpUrl);
            return false;
        }

        return $webpUrl;
    }

    public function getWebpHelper($imageUrl)
    {
        return $this->convertImageUrlToWebp($imageUrl) ?: $imageUrl;
    }

    /**
     * @param $imageList
     *
     * @return array
     */
    protected function getScriptHtmlLines($imageList)
    {
        $newHtml = array();

        $newHtml[] = '<script>';
        $newHtml[] = 'var SKIN_URL = \'' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . '\';';
        $webpCookie = (int)Mage::app()->getRequest()->getCookie('webp', 0);

        $newHtml[] = 'var WEBP_COOKIE = ' . $webpCookie . ';';
        $newHtml[] = 'if(webpReplacements == null) { var webpReplacements = new Object(); }';
        foreach ($imageList as $name => $value) {
            $newHtml[] = 'webpReplacements[\'' . $name . '\'] = ' . json_encode($value);
        }
        $newHtml[] = '</script>';

        return $newHtml;
    }

    /**
     * @param string $imagePath
     *
     * @return mixed
     */
    protected function getImageUrlFromPath($imagePath)
    {
        $systemPaths = $this->helper->getSystemPaths();

        foreach ($systemPaths as $systemPath) {
            if (strstr($imagePath, $systemPath['path'])) {
                return str_replace($systemPath['path'], $systemPath['url'], $imagePath);
            }
        }
    }

    /**
     * @param string $imageUrl
     *
     * @return mixed
     */
    protected function getImagePathFromUrl($imageUrl)
    {
        $systemPaths = $this->helper->getSystemPaths();

        if (preg_match('/^http/', $imageUrl)) {
            foreach ($systemPaths as $systemPath) {
                if (strstr($imageUrl, $systemPath['url'])) {
                    return str_replace($systemPath['url'], $systemPath['path'], $imageUrl);
                }
            }
        }
    }

    /**
     * @param $html
     * @param $scriptLines
     *
     * @return mixed|string
     */
    protected function addScriptToBody($html, $scriptLines)
    {
        $script = implode("\n", $scriptLines) . "\n";
        if (strstr($html, '</body>')) {
            $html = str_replace('</body>', $script . '</body>', $html);
        } else {
            $html = $html . $script;
        }

        return $html;
    }
}
