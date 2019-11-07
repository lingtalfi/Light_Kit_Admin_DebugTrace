<?php


namespace Ling\Light_Kit_Admin_DebugTrace\Service;


use Ling\BabyYaml\BabyYamlUtil;
use Ling\Bat\FileSystemTool;
use Ling\Bat\FileTool;
use Ling\Light\Core\Light;
use Ling\Light\Events\LightEvent;
use Ling\Light\Http\HttpRequestInterface;
use Ling\Light\ServiceContainer\LightServiceContainerInterface;
use Ling\Light_Initializer\Initializer\LightInitializerInterface;

/**
 * The LightKitAdminDebugTraceService class.
 */
class LightKitAdminDebugTraceService implements LightInitializerInterface
{

    /**
     * This property holds the container for this instance.
     * @var LightServiceContainerInterface
     */
    protected $container;

    /**
     * This property holds the targetFile for this instance.
     * @var string
     */
    protected $targetFile;


    /**
     * This property holds the httpRequestFilters for this instance.
     * @var array
     */
    protected $httpRequestFilters;

    /**
     * This property holds the _isAcceptedRequest for this instance.
     * Assuming that if we accept the request, it's for the whole process.
     * Null means the flag has not been set yet.
     *
     * @var bool
     */
    private $_isAcceptedRequest;


    /**
     * Builds the LightKitAdminDebugTraceService instance.
     */
    public function __construct()
    {
        $this->container = null;
        $this->targetFile = null;
        $this->httpRequestFilters = [];
        $this->_isAcceptedRequest = true;
    }

    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * @implementation
     */
    public function initialize(Light $light, HttpRequestInterface $httpRequest)
    {

        $this->testRequest($httpRequest);

        if (true === $this->isAcceptedRequest()) {


            $this->resetFile();
            $info = [
                "http_request" => [
                    'url' => $httpRequest->getUri(),
                    '$_GET' => $httpRequest->getGet(),
                    '$_POST' => $httpRequest->getPost(),
                    '$_FILES' => $httpRequest->getFiles(),
                    '$_COOKIE' => $httpRequest->getCookie(),
                ],
            ];
            $this->appendSection($info);
        }
    }


    /**
     * Callable for the Light.on_route_found event provided by @page(the Light framework).
     *
     * @param LightEvent $event
     * @param string $eventName
     * @throws \Exception
     */
    public function onRouteFound(LightEvent $event, string $eventName)
    {
        if (true === $this->isAcceptedRequest()) {
            $route = $event->getVar("route");
            $this->appendSection(["route" => $route]);
        }
    }


    /**
     * Callable for the Light_Kit_Admin.on_page_rendered_before event provided by @page(the Light_Kit_Admin plugin).
     *
     * @param LightEvent $event
     * @param string $eventName
     * @throws \Exception
     */
    public function onPageRenderedBefore(LightEvent $event, string $eventName)
    {
        if (true === $this->isAcceptedRequest()) {
            $page = $event->getVar("page");
            $this->appendSection(["kit_admin_page" => $page]);
        }
    }


    /**
     * Callable for the Light_Kit.on_page_conf_ready event provided by @page(the Light_Kit plugin).
     *
     * @param LightEvent $event
     * @param string $eventName
     * @throws \Exception
     */
    public function onKitPageConfReady(LightEvent $event, string $eventName)
    {
        if (true === $this->isAcceptedRequest()) {
            $conf = $event->getVar("pageConf");
            $zones = $conf['zones'];

            $myZones = [];
            foreach ($zones as $name => $widgets) {
                foreach ($widgets as $widget) {
                    $myZones[$name][] = [
                        "name" => $widget['name'],
                        "className" => $widget['className'] . " (" . $widget['type'] . ")",
                        "widgetFile" => $widget['widgetDir'] . "/" . $widget['template'],
                    ];
                }
            }


            $compactConf = [
                'layout' => $conf['layout'],
                'zones' => $myZones,
            ];
            $this->appendSection(["kit_admin_conf" => $compactConf]);
        }
    }




    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * Sets the container.
     *
     * @param LightServiceContainerInterface $container
     */
    public function setContainer(LightServiceContainerInterface $container)
    {
        $this->container = $container;
    }


    /**
     * Sets the targetFile.
     *
     * @param string $targetFile
     */
    public function setTargetFile(string $targetFile)
    {
        $this->targetFile = $targetFile;
    }

    /**
     * Sets the httpRequestFilters.
     *
     * @param array $httpRequestFilters
     */
    public function setHttpRequestFilters(array $httpRequestFilters)
    {
        $this->httpRequestFilters = $httpRequestFilters;
    }



    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * Appends a section to the target file.
     * The section is an array of key/value pairs.
     *
     * @param array $section
     */
    protected function appendSection(array $section)
    {
        $s = BabyYamlUtil::getBabyYamlString($section);
        FileTool::append($s . PHP_EOL . PHP_EOL, $this->targetFile);
    }


    /**
     * Empty the target file.
     */
    protected function resetFile()
    {
        FileSystemTool::mkfile($this->targetFile, "");
    }


    /**
     * Returns whether the http request is valid, based on the http request filters
     * defined for this instance.
     *
     * @param HttpRequestInterface $httpRequest
     * @return bool
     */
    protected function testRequest(HttpRequestInterface $httpRequest): bool
    {
        $this->_isAcceptedRequest = true;
        $uri = $httpRequest->getUri();


        $urlIgnoreIfStartWith = $this->httpRequestFilters['urlIgnoreIfStartWith'] ?? [];
        if (false === is_array($urlIgnoreIfStartWith)) {
            $urlIgnoreIfStartWith = [$urlIgnoreIfStartWith];
        }
        foreach ($urlIgnoreIfStartWith as $prefix) {
            if (0 === strpos($uri, $prefix)) {
                $this->_isAcceptedRequest = false;
            }
        }
        return $this->_isAcceptedRequest;
    }

    /**
     * Returns whether the current http request has been accepted.
     * @return bool
     */
    protected function isAcceptedRequest(): bool
    {
        return $this->_isAcceptedRequest;
    }
}