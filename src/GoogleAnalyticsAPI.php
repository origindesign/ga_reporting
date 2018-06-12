<?php
/**
 * @file Contains \Drupal\ga_reporting\GoogleAnalyticsAPI
 */

namespace Drupal\ga_reporting;

use Google_Client;
use Google_Service_AnalyticsReporting;
//use Google_Auth_AssertionCredentials;
use Google_Service_AnalyticsReporting_DateRange;
use Google_Service_AnalyticsReporting_Metric;
use Google_Service_AnalyticsReporting_Dimension;
use Google_Service_AnalyticsReporting_ReportRequest;
use Google_Service_AnalyticsReporting_GetReportsRequest;
use Google_Service_AnalyticsReporting_DimensionFilter;
use Google_Service_AnalyticsReporting_DimensionFilterClause;
use Google_Service_AnalyticsReporting_OrderBy;



class GoogleAnalyticsAPI{



    protected $client;
    protected $viewId;



    public function initializeClient($viewId) {

        $this->viewId = $viewId;

        // Get config
        $config = \Drupal::config('ga_reporting.credentials');

        // Get file
        if($config->get('credentials_file') != ''){
            $file = file_load($config->get('credentials_file'));
            if(isset($file)){
                $name = $file->getFileName();
                $url = \Drupal::service('file_system')->realpath("private://").'/credentials/'.$name;
            }
        }

        if(isset($url)){

            $client = new Google_Client();
            $client->setScopes('https://www.googleapis.com/auth/analytics.readonly');
            $client->setApplicationName('Analytics Reporting');
            $analyticsreporting = new Google_Service_AnalyticsReporting($client);
            $client->setAuthConfig($url);
            $this->client = $analyticsreporting;

        }else{
            drupal_set_message('You must upload a Google API credentials file at /admin/config/services/ga_credentials','warning');
        }

        return false;

    }



    /**
     *
     * @param $pageUrl, $dateFrom, $dateTo
     * @param $dateFrom, $dateTo
     * @param $dateTo
     * @return
     */

    public function getPageMetrics($pageUrl, $dateFrom, $dateTo){
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($dateFrom);
        $dateRange->setEndDate($dateTo);
        $metricKeys= [
            'ga:pageviews' => 'pageviews',
            'ga:users' => 'users',
            'ga:avgTimeOnPage' => 'avgTimeOnPage',
            'ga:bounceRate' => 'bounceRate'
        ];
        $metrics = [];
        foreach($metricKeys as $metric => $alias) {
            $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
            $reportingMetric->setExpression($metric);
            $reportingMetric->setAlias($alias);
            array_push($metrics, $reportingMetric);
        }
        $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
        $filters->setDimensionName('ga:pagepath');
        $filters->setExpressions([$pageUrl]);
        $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
        $filterClause->setFilters([$filters]);
        $res = $this->performRequest([
            'dateRange' => $dateRange,
            'metrics' => $metrics,
            'filterClause' => $filterClause
        ]);
        $headers = $res['modelData']['reports'][0]['columnHeader']['metricHeader']['metricHeaderEntries'];
        $metrics = $res['modelData']['reports'][0]['data']['rows'][0]['metrics'][0]['values'];
        $resultSet = [];
        for($i = 0; $i < count($headers); $i++) {
            $headerName = $headers[$i]['name'];
            $columnType = $headers[$i]['type'];
            if ($columnType == 'INTEGER') {
                $metricValue = intval($metrics[$i]);
            } elseif ($columnType == 'FLOAT' ||
                $columnType == 'TIME' ||
                $columnType == 'PERCENT') {
                $metricValue = floatval($metrics[$i]);
            } else {
                $metricValue = $metrics[$i];
            }
            $resultSet[$headerName] = $metricValue;
        }
        return $resultSet;
    }



    /**
     *
     * @param $pageUrl, $dateFrom, $dateTo
     * @param $dateFrom, $dateTo
     * @param $dateTo
     * @return
     */

    public function getPageViewsByTrafficSource($pageUrl, $dateFrom, $dateTo) {
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($dateFrom);
        $dateRange->setEndDate($dateTo);
        $metricKeys= [
            'ga:pageviews' => 'pageviews'
        ];
        $metrics = [];
        foreach($metricKeys as $metric => $alias) {
            $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
            $reportingMetric->setExpression($metric);
            $reportingMetric->setAlias($alias);
            array_push($metrics, $reportingMetric);
        }
        $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
        $filters->setDimensionName('ga:pagepath');
        $filters->setExpressions([$pageUrl]);

        $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
        $filterClause->setFilters([$filters]);

        $channelGroupingDimension = new Google_Service_AnalyticsReporting_Dimension();
        $channelGroupingDimension->setName('ga:channelGrouping');

        $res = $this->performRequest([
            'dateRange' => $dateRange,
            'metrics' => $metrics,
            'filterClause' => $filterClause,
            'dimensions' => [$channelGroupingDimension]
        ]);
        $resultSet = [];
        $rows = $res['modelData']['reports'][0]['data']['rows'];
        foreach($rows as $row) {
            $resultSet[$row['dimensions'][0]] = intval($row['metrics'][0]['values'][0]);
        }
        return $resultSet;
    }



    public function getPageViewsByCountry($pageUrl, $dateFrom, $dateTo) {
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($dateFrom);
        $dateRange->setEndDate($dateTo);
        $metricKeys= [
            'ga:pageviews' => 'pageviews'
        ];
        $metrics = [];
        foreach($metricKeys as $metric => $alias) {
            $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
            $reportingMetric->setExpression($metric);
            $reportingMetric->setAlias($alias);
            array_push($metrics, $reportingMetric);
        }
        $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
        $filters->setDimensionName('ga:pagepath');
        $filters->setExpressions([$pageUrl]);
        $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
        $filterClause->setFilters([$filters]);
        $countryDimension = new Google_Service_AnalyticsReporting_Dimension();
        $countryDimension->setName('ga:country');
        $res = $this->performRequest([
            'dateRange' => $dateRange,
            'metrics' => $metrics,
            'filterClause' => $filterClause,
            'dimensions' => [$countryDimension],
            'orderBys' => [
                'fieldName' => 'ga:pageviews',
                'sortOrder' => 'DESCENDING'
            ],
            'pageSize' => 10
        ]);
        $rows = $res['reports'][0]['data']['rows'];
        $resultSet = [];
        foreach($rows as $row) {
            $resultSet[$row['dimensions'][0]] = intval($row['metrics'][0]['values'][0]);
        }
        return $resultSet;
    }



    protected function performRequest($params) {
        $request = new Google_Service_AnalyticsReporting_ReportRequest();
        $request->setViewId($this->viewId);
        if (isset($params['dateRange'])) {
            $request->setDateRanges($params['dateRange']);
        }
        if (isset($params['metrics'])) {
            $request->setMetrics($params['metrics']);
        }
        if (isset($params['filterClause'])) {
            $request->setDimensionFilterClauses([$params['filterClause']]);
        }
        if (isset($params['orderBys'])) {
            $request->setOrderBys($params['orderBys']);
        }
        if (isset($params['pageSize'])) {
            $request->setPageSize(10);
        }
        if (isset($params['dimensions'])) {
            $request->setDimensions($params['dimensions']);
        }
        $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests([$request]);
        return $this->client->reports->batchGet($body);
    }


    /**
     * @param $dateFrom
     * @param $dateTo
     * @param $metricKeys
     * @param $dimensionKeys
     * @param $dimensionFilterKeys
     * @return mixed
     */
    public function getReport($dateFrom, $dateTo, $metricKeys, $dimensionKeys, $dimensionFilterKeys) {

        // Create the DateRange object.
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($dateFrom);
        $dateRange->setEndDate($dateTo);

        // Setup metrics
        $metrics = [];
        foreach($metricKeys as $metric => $alias) {
            $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
            $reportingMetric->setExpression($metric);
            $reportingMetric->setAlias($alias);
            array_push($metrics, $reportingMetric);
        }

        // Setup Dimensions
        $dimensions = [];
        foreach($dimensionKeys as $name) {
            $dimension = new Google_Service_AnalyticsReporting_Dimension();
            $dimension->setName($name);
            array_push($dimensions, $dimension);
        }

        // Setup dimension filters
        $dimensionFilters = [];
        foreach($dimensionFilterKeys as $name => $expression) {
            $dimensionFilter = new Google_Service_AnalyticsReporting_DimensionFilter();
            $dimensionFilter->setDimensionName($name);
            $dimensionFilter->setOperator('EXACT');
            $dimensionFilter->setExpressions(array($expression));
            array_push($dimensionFilters, $dimensionFilter);
        }

        // Setup dimension filter clauses
        $filterClauses = [];
        foreach($dimensionFilters as $filter) {
            $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
            $filterClause->setFilters($filter);
            array_push($filterClauses, $filterClause);
        }

        // Create the ReportRequest object.
        $request = new Google_Service_AnalyticsReporting_ReportRequest();
        $request->setViewId($this->viewId);
        $request->setDateRanges($dateRange);
        $request->setDimensions($dimensions);
        $request->setDimensionFilterClauses($filterClauses);
        $request->setMetrics($metrics);

        $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests( array( $request) );
        return $this->client->reports->batchGet( $body );
    }



    /**
     * @param $reports
     * @return array
     */
    public function getResults($reports) {

        $result = [];

        for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
            $report = $reports[ $reportIndex ];

            $header = $report->getColumnHeader();
            $dimensionHeaders = $header->getDimensions();
            $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
            $rows = $report->getData()->getRows();

            for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[ $rowIndex ];
                $dimensions = $row->getDimensions();
                $metrics = $row->getMetrics();

                for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
                    $result[$rowIndex][$dimensionHeaders[$i]] = $dimensions[$i];
                }

                for ($j = 0; $j < count($metrics); $j++) {
                    $values = $metrics[$j]->getValues();
                    for ($k = 0; $k < count($values); $k++) {
                        $entry = $metricHeaders[$k];
                        $result[$rowIndex][$entry->getName()] = $values[$k];
                    }
                }

            }
        }

        return $result;

    }



}