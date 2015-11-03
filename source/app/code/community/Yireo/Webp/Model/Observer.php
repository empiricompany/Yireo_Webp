<?php
/**
 * Webp plugin for Magento 
 *
 * @package     Yireo_Webp
 * @author      Yireo (http://www.yireo.com/)
 * @copyright   Copyright 2015 Yireo (http://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

class Yireo_Webp_Model_Observer
{
    protected function isAllowedBlock($block)
    {
        $allowedBlocks = array('root');

        if(in_array($block->getNameInLayout(), $allowedBlocks)) {
            return true;
        }

        return false;
    }

    /**
     * Listen to the event core_block_abstract_to_html_after
     *
     * @parameter Varien_Event_Observer $observer
     * @return $this
     */
    public function coreBlockAbstractToHtmlAfter($observer)
    {
        if($this->getHelper()->enabled() == false) {
            return $this;
        }

        $transport = $observer->getEvent()->getTransport();
        $block = $observer->getEvent()->getBlock();
        $systemPaths = $this->getHelper()->getSystemPaths();

        if($this->isAllowedBlock($block)) {
            $html = $transport->getHtml();

            $newHtml = array();
            if(preg_match_all('/\ src=\"([^\"]+)\.(png|jpg|jpeg)/i', $html, $matches)) {

                $imageList = array();
                foreach($matches[0] as $index => $match) {

                    // Convert the URL to a valid path
                    $imagePath = null;
                    $imageUrl = $matches[1][$index].'.'.$matches[2][$index];
                    if(preg_match('/^http/', $imageUrl)) {
                        foreach($systemPaths as $systemPath) {
                            if(strstr($imageUrl, $systemPath['url'])) {
                                $imagePath = str_replace($systemPath['url'], $systemPath['path'].DS, $imageUrl);
                                break;
                            }
                        }
                    }

                    // If this failed, don't continue
                    if(!$this->getFileHelper()->exists($imagePath)) {
                        continue;
                    }
    
                    // Construct the new WebP image-name
                    $webpPath = $this->getHelper()->convertToWebp($imagePath);

                    // If this failed, don't continue
                    if(empty($webpPath) || $this->getFileHelper()->exists($webpPath) == false) {
                        continue;
                    }

                    // Convert the path back to a valid URL
                    $webpUrl = null;
                    foreach($systemPaths as $systemPath) {
                        if(strstr($webpPath, $systemPath['path'])) {
                            $webpUrl = str_replace($systemPath['path'], $systemPath['url'].DS, $webpPath);
                            break;
                        }
                    }

                    // Replace the img tag in the HTML
                    $htmlTag = $matches[0][$index];
                    $newHtmlTag = str_replace('src="'.$imageUrl, 'data-img="'.md5($imageUrl), $htmlTag);
                    $html = str_replace($htmlTag, $newHtmlTag, $html);
    
                    // Add the images to the return-array
                    $imageList[md5($imageUrl)] = array('orig' => $imageUrl, 'webp' => $webpUrl);
                }

                // Add a JavaScript-list to the HTML-document
                if(!empty($imageList)) {
                    $newHtml[] = '<script>';
                    $newHtml[] = 'var SKIN_URL = \''.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN).'\';';
                    $webpCookie = (int)Mage::app()->getRequest()->getCookie('webp', 0);

                    $newHtml[] = 'var WEBP_COOKIE = '.$webpCookie.';';
                    $newHtml[] = 'if(webpReplacements == null) { var webpReplacements = new Object(); }';
                    foreach($imageList as $name => $value) {
                        $newHtml[] = 'webpReplacements[\''.$name.'\'] = '.json_encode($value);
                    }
                    $newHtml[] = '</script>';
                }
            }

            if($block->getNameInLayout() == 'root') {
                $newHtml[] = '<script type="text/javascript" src="'.Mage::getBaseUrl('js').'webp/jquery.detect.js"></script>';
            }

            $newHtml = implode("\n", $newHtml)."\n";
            if(strstr($html, '</body>')) {
                $html = str_replace('</body>', $newHtml.'</body>', $html);
            } else {
                $html = $html.$newHtml;
            }

            $transport->setHtml($html);
        }

        return $this;
    }

    /**
     * Return the helper class
     *
     * @return Yireo_Webp_Helper_Data 
     */
    public function getHelper()
    {
        return Mage::helper('webp');
    }

    /**
     * Return the file helper class
     *
     * @return Yireo_Webp_Helper_File
     */
    public function getFileHelper()
    {
        return Mage::helper('webp/file');
    }
}
