<?php

namespace ReportPortalBasic\Service;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use ReportPortalBasic\Enum\ItemStatusesEnum;
use Symfony\Component\Yaml\Yaml;

/**
 * Report portal HTTP service.
 * Provides basic methods to collaborate with Report portal.
 *
 * @author Mikalai_Kabzar
 */
class ReportPortalHTTPService
{

    /**
     *
     * @var string
     */
    public const DAFAULT_LAUNCH_MODE = 'DEFAULT';

    /**
     *
     * @var string
     */
    protected const EMPTY_ID = 'empty id';

    /**
     *
     * @var string
     */
    protected const DEFAULT_FEATURE_DESCRIPTION = '';

    /**
     *
     * @var string
     */
    protected const DEFAULT_SCENARIO_DESCRIPTION = '';

    /**
     *
     * @var string
     */
    protected const DEFAULT_STEP_DESCRIPTION = '';

    /**
     *
     * @var string
     */
    protected const FORMAT_DATE = 'Y-m-d\TH:i:s';

    /**
     *
     * @var string
     */
    protected const BASE_URI_TEMPLATE = 'http://%s/api/';

    /**
     *
     * @var string
     */
    protected static $timeZone;

    /**
     *
     * @var string
     */
    protected static $UUID;

    /**
     *
     * @var string
     */
    protected static $baseURI;

    /**
     *
     * @var string
     */
    protected static $host;

    /**
     *
     * @var string
     */
    protected static $projectName;

    /**
     *
     * @var string
     */
    protected static $launchID = self::EMPTY_ID;

    /**
     *
     * @var string
     */
    protected static $rootItemID = self::EMPTY_ID;

    /**
     *
     * @var string
     */
    protected static $featureItemID = self::EMPTY_ID;

    /**
     *
     * @var string
     */
    protected static $scenarioItemID = self::EMPTY_ID;

    /**
     *
     * @var string
     */
    protected static $stepItemID = self::EMPTY_ID;

    /**
     *
     * @var \GuzzleHttp\Client
     */
    protected static $client;

    function __construct()
    {
        self::$client = new Client([
            'base_uri' => self::$baseURI
        ]);
    }

    /**
     * Check if any suite has running status
     *
     * @return boolean - true if any suite has running status
     */
    public static function isSuiteRunned()
    {
        return self::$rootItemID != self::EMPTY_ID;
    }

    /**
     * Check if any step has running status
     *
     * @return boolean - true if any step has running status
     */
    public static function isStepRunned()
    {
        return self::$stepItemID != self::EMPTY_ID;
    }

    /**
     * Check if any scenario has running status
     *
     * @return boolean - true if any scenario has running status
     */
    public static function isScenarioRunned()
    {
        return self::$scenarioItemID != self::EMPTY_ID;
    }

    /**
     * Check if any feature has running status
     *
     * @return boolean - true if any feature has running status
     */
    public static function isFeatureRunned()
    {
        return self::$featureItemID != self::EMPTY_ID;
    }

    /**
     * Set configuration for Report portal from yaml file
     *
     * @param string $yamlFilePath
     *            - path to configuration file
     */
    public static function configureReportPortalHTTPService(string $yamlFilePath)
    {
        $yamlArray = Yaml::parse($yamlFilePath);
        self::$UUID = $yamlArray['UUID'];
        self::$host = $yamlArray['host'];
        self::$baseURI = sprintf(self::BASE_URI_TEMPLATE, self::$host);
        self::$projectName = $yamlArray['projectName'];
        self::$timeZone = $yamlArray['timeZone'];
    }

    /**
     * Launch test run
     *
     * @param string $name
     *            - name of test launch
     * @param string $description
     *            - description of test run
     * @param string $mode
     *            - mode
     * @param array $tags
     *            - array with tags of test run
     * @return ResponseInterface - result of request
     */
    public static function launchTestRun(string $name, string $description, string $mode, array $tags)
    {
        $result = self::$client->post('v1/' . self::$projectName . '/launch', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'description' => $description,
                'mode' => $mode,
                'name' => $name,
                'start_time' => self::getTime(),
                'tags' => $tags
            )
        ));
        self::$launchID = self::getValueFromResponse('id', $result);
        return $result;
    }

    /**
     * Finish test run
     *
     * @param string $runStatus
     *            - status of test run
     * @return ResponseInterface - result of request
     */
    public static function finishTestRun(string $runStatus)
    {
        $result = self::$client->put('v1/' . self::$projectName . '/launch/' . self::$launchID . '/finish', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'end_time' => self::getTime(),
                'status' => $runStatus
            )
        ));
        return $result;
    }

    /**
     * Create root item
     *
     * @param string $name
     *            - root item name
     * @param string $description
     *            - root item description
     * @param array $tags
     *            - array with tags
     * @return ResponseInterface - result of request
     */
    public static function createRootItem(string $name, string $description, array $tags)
    {
        $result = self::$client->post('v1/' . self::$projectName . '/item', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'description' => $description,
                'launch_id' => self::$launchID,
                'name' => $name,
                'start_time' => self::getTime(),
                "tags" => $tags,
                "type" => "SUITE"
            )
        ));
        self::$rootItemID = self::getValueFromResponse('id', $result);
        return $result;
    }

    /**
     * Finish root item
     *
     * @param string $resultStatus
     *            - result of root item
     * @return ResponseInterface - result of request
     */
    public static function finishRootItem(string $resultStatus)
    {
        $result = self::finishItem(self::$rootItemID, ItemStatusesEnum::PASSED, '');
        self::$rootItemID = self::EMPTY_ID;
        return $result;
    }

    /**
     * Add a log message to item
     *
     * @param string $item_id
     *            - item id to add log message
     * @param string $message
     *            - log message
     * @param string $logLevel
     *            - log level of log message
     * @return ResponseInterface - result of request
     */
    protected static function addLogMessage(string $item_id, string $message, string $logLevel)
    {
        $result = self::$client->post('v1/' . self::$projectName . '/log', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'item_id' => $item_id,
                'message' => $message,
                'time' => self::getTime(),
                'level' => $logLevel
            )
        ));
        return $result;
    }

    /**
     * Add log with picture.
     *
     * @param string $pictureAsString - picture as string.
     * @param string $item_id - current step item_id.
     * @param string $message - message for log.
     * @param string $logLevel - log level
     * @param string $pictureContentType - picture content type (png, jpeg, etc.)
     * @return ResponseInterface - response
     */
    public static function addPictureToLogMessage(string $pictureAsString, string $item_id, string $message, string $logLevel, string $pictureContentType)
    {
        if (self::isStepRunned()) {
            $multipart = new MultipartStream([
                [
                    'name' => 'json_request_part',
                    'contents' => json_encode([['file' => ['name' => 'picture'],
                        'item_id' => $item_id,
                        'message' => $message,
                        'time' => self::getTime(),
                        'level' => $logLevel]]),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Content-Transfer-Encoding' => '8bit'
                    ]
                ],
                [
                    'name' => 'binary_part',
                    'contents' => $pictureAsString,
                    'filename' => 'picture',
                    'headers' => [
                        'Content-Type' => 'image/' . $pictureContentType,
                        'Content-Transfer-Encoding' => 'binary'
                    ]
                ]
            ]);
            $request = new Request(
                'POST',
                'v1/' . self::$projectName . '/log',
                [],
                $multipart
            );
            $result = self::$client->send($request);
            return $result;
        }
    }

    /**
     * Get value from response.
     *
     * @param string $lookForRequest
     *            - string to find value
     * @param ResponseInterface $response
     * @return string value by $lookForRequest.
     */
    protected static function getValueFromResponse(string $lookForRequest, ResponseInterface $response)
    {
        $array = json_decode($response->getBody()->getContents());
        return $array->{$lookForRequest};
    }

    /**
     * Start child item.
     *
     * @param string $parentItemID
     *            - id of parent item.
     * @param string $description
     *            - item description
     * @param string $name
     *            - item name
     * @param string $type
     *            - item type
     * @param array $tags
     *            - array with tags
     * @return ResponseInterface - result of request
     */
    protected static function startChildItem(string $parentItemID, string $description, string $name, string $type, array $tags)
    {
        $result = self::$client->post('v1/' . self::$projectName . '/item/' . $parentItemID, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'description' => $description,
                'launch_id' => self::$launchID,
                'name' => $name,
                'start_time' => self::getTime(),
                'tags' => $tags,
                'type' => $type
            )
        ));
        return $result;
    }

    /**
     * Finish item by id
     *
     * @param string $itemID
     *            - test item ID
     * @param string $status
     *            - status of test item
     * @param string $description
     *            - description of test item
     * @return ResponseInterface - result of request
     */
    protected static function finishItem(string $itemID, string $status, string $description)
    {
        $result = self::$client->put('v1/' . self::$projectName . '/item/' . $itemID, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'bearer ' . self::$UUID
            ),
            'json' => array(
                'description' => $description,
                'end_time' => self::getTime(),
                'status' => $status
            )
        ));
        return $result;
    }

    /**
     * Get local time
     *
     * @return string with local time
     */
    private static function getTime()
    {
        return date(self::FORMAT_DATE) . self::$timeZone;
    }
}