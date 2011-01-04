<?php

require_once 'SearchEngine.php';

define('ESFS_MAX_RESULTS', 1000);
define('ESFS_SRW_VERSION', '1.1');

// XML namespaces used in ESFS responses
define('XMLNS_SRW',  'http://www.loc.gov/zing/srw/');
define('XMLNS_DC',   'http://purl.org/dc/elements/1.1/');
define('XMLNS_DSM',  'http://www.edna.edu.au/schema/edna_dsm_v1p0/');
//define('XMLNS_DIAG', 'http://www.loc.gov/zing/srw/diagnostic/');
//define('XMLNS_XCQL', 'http://www.loc.gov/zing/cql/xcql/');

/**
 * Search Engine wrapper for the Education Sector Federated Search
 * engine (http://www.esfs.govt.nz).
 */
class SearchEngine_esfs extends SearchEngine{

    var $searchprovidername = 'esfs';

    /**
     * Parse the query parameters to create the search URL
     *
     * @param string $query      Query text
     * @param int    $page       The page number where are on (first page = 0)
     * @param int    $searchid   ID for this search
     * @param class  $blockconfig All configuration settings for this instance of the block
     * @param string $courselink Link to the course in case of errors
     */
    function set_query($query, $page, $searchid, $blockconfig, $courselink)
    {
        parent::set_query($query, $page, $searchid, $blockconfig, $courselink);

        $clienttoken = get_config(NULL, 'block_extsearch_esfs_client_token');
        if (empty($clienttoken)) {
            print_error('error:missingesfsclienttoken', 'block_extsearch', $this->courselink);
            return false;
        }

        $querytext = urlencode('cql.serverChoice all "'.$this->query.'"');
        $this->searchurl = 'http://www.esfs.govt.nz/dsm/sru/search?operation=searchRetrieve&query='.$querytext.
            '&version='.ESFS_SRW_VERSION.'&x-user='.$clienttoken.'&x-mr='.ESFS_MAX_RESULTS;
        $this->searchurl .= '&x-ss=waitfast&sortKeys=relevance&x-sr=all';

        if ($this->page > 0) {
            // Note: first record = 1 (not 0)
            $startrecord = $this->page * $this->results->perpage + 1;
            $this->searchurl .= "&startRecord=$startrecord";
        }
        if ($searchid != 0) {
            // Search token from previous resultset
            $this->searchurl .= "&x-token=$searchid";
        }

        return true;
    }
}

/**
 * Extract the first child node matching the given name and
 * namespace. Optionally filering on a specific attribute.
 *
 * @return mixed The DOMNode object or FALSE in case of errors
 */
function get_single_node($xmlnode, $namespaceuri, $nodename, $attributename='', $attributevalue='')
{
    $allsubnodes = $xmlnode->getElementsByTagNameNS($namespaceuri, $nodename);
    if (!$allsubnodes or $allsubnodes->length < 1) {
        //debugging("No nodes named '$nodename' (namespace=$namespaceuri) under $xmlnode->nodeName", DEBUG_DEVELOPER);
        return false;
    }

    if (empty($attributename)) {
        // No attribute filter
        if ($allsubnodes->length > 1) {
            debugging("More than one node named '$nodename' (namespace=$namespaceuri) under $xmlnode->nodeName", DEBUG_NORMAL);
            return false;
        }
    }
    else {
        // Look for attributename=attributevalue (first match wins)
        foreach ($allsubnodes as $node) {
            if ($node->getAttribute($attributename) == $attributevalue) {
                return $node;
            }
        }
        //debugging("No nodes named '$nodename' (namespace=$namespaceuri) under $xmlnode->nodeName with attribute $attributename = $attributevalue", DEBUG_DEVELOPER);
        return false;
    }
    return $allsubnodes->item(0);
}

/**
 * Extract the value of the first child node matching the given name
 * and namespace.
 *
 * @return mixed The node value or FALSE in case of errors
 */
function get_node_value($xmlnode, $namespaceuri, $nodename, $attributename='', $attributevalue='')
{
    if ($node = get_single_node($xmlnode, $namespaceuri, $nodename, $attributename, $attributevalue)) {
        return $node->nodeValue;
    }
    else {
        return false;
    }
}

/**
 * Search Results parser for the Education Sector Federated Search
 * engine (http://www.esfs.govt.nz).
 */
class SearchResults_esfs extends SearchResults
{
    /** Images not worth displaying to the user */
    var $brokenimages = array('http://www.scienceimage.csiro.au/index.cfm?event=site.image.thumbnail');

    /**
     * Load Search Results from XML
     *
     * @param string $xmlresults Search results in an XML string
     * @return true if the XML was loaded succesfully, false otherwise
     */
    function load_results($xmlresults)
    {
        if (!$domdocument = DOMDocument::loadXML($xmlresults)) {
            debugging('Could not load XML document', DEBUG_NORMAL);
            return false;
        }

        if ($responsenode = get_single_node($domdocument, XMLNS_SRW, 'searchRetrieveResponse')) {
            // Check API version and warn about unexpected versions
            if (get_node_value($responsenode, XMLNS_SRW, 'version') != ESFS_SRW_VERSION) {
                debugging('Unexpected SRW version (not '.ESFS_SRW_VERSION.')', DEBUG_ALL);
            }

            $this->numresults = get_node_value($responsenode, XMLNS_SRW, 'numberOfRecords');
            $this->resultsetid = get_node_value($responsenode, XMLNS_SRW, 'resultSetId');

            if ($records = get_single_node($responsenode, XMLNS_SRW, 'records')) {
                $recordnodes = $records->getElementsByTagNameNS(XMLNS_SRW, 'record');

                if ($recordnodes->length > $this->numresults) {
                    debugging('More records in this batch of results than in the entire query', DEBUG_ALL);
                }

                $this->parse_records($recordnodes);
            }

            // TODO: check for SRW diagnostic nodes

            if ($extraresponsedata = get_single_node($responsenode, XMLNS_SRW, 'extraResponseData')) {
                if ($sources = get_single_node($extraresponsedata, XMLNS_DSM, 'sources')
                    and $sourcenodes = $sources->getElementsByTagNameNS(XMLNS_DSM, 'source')) {
                    $this->parse_sources($sourcenodes);
                }
                if ($summary = get_single_node($extraresponsedata, XMLNS_DSM, 'summary')) {
                    $this->querytiming = get_node_value($summary, XMLNS_DSM, 'timetaken');
                    //$this->numresults = get_node_value($summary, XMLNS_DSM, 'found');
                    //$this->numresults = get_node_value($summary, XMLNS_DSM, 'total');
                    //$this->numresults = get_node_value($summary, XMLNS_DSM, 'theoreticaltotal');
                }
            }
        }
        else {
            debugging('XML document is not a searchRetrieveResponse', DEBUG_NORMAL);
            return false;
        }

        return true;
    }

    /**
     * Print nicely formatted search results
     *
     * @param string  $choose   HTML ID of the parent element to set (picker mode)
     */
    function print_results($choose='')
    {
        if (!empty($this->records)) {
            foreach ($this->records as $record) {
                print '<p>';
                if (!empty($choose)) {
                    $this->print_choose_button($record->url);
                }
                $this->print_format($record->format);
                $this->print_title($record->title, $record->url);
                $this->print_description($record->description, $record->preview);
                print '<br/>';
                $this->print_source($record->source);
                $this->print_date($record->date);
                print '</p>';
            }
        }
        else {
            print get_string('noresultsfound', 'block_extsearch');
        }
    }

    /**
     * Internal function to print the document format if it's
     * recognised.
     */
    function print_format($taintedformat)
    {
        $format = '';

        // Look for a few common MIME types
        $lcformat = strtolower(trim($taintedformat));
        if (strpos($lcformat, 'pdf') !== false) {
            $format = 'PDF';
        }
        else if (strpos($lcformat, 'msword') !== false) {
            $format = 'DOC';
        }
        else if (strpos($lcformat, 'powerpoint') !== false) {
            $format = 'PPT';
        }

        if (!empty($format)) {
            print '['.format_string($format).'] ';
        }
    }

    /**
     * Internal function for parsing the records from the results set.
     *
     * @param $recordnodes DOMNodeList Nodes inside a response's srw:records
     */
    function parse_records($recordnodes)
    {
        $this->records = array();

        $i=0;
        foreach ($recordnodes as $node) {
            // Sanity checks
            if (get_node_value($node, XMLNS_SRW, 'recordPacking') != 'XML') {
                debugging("Unexpected recordPacking value for record $i", DEBUG_ALL);
            }
            if (get_node_value($node, XMLNS_SRW, 'recordSchema') != 'info:srw/schema/1/dc-v1.1') {
                debugging("Unexpected recordSchema value for record $i", DEBUG_ALL);
            }

            // Record data
            if ($recorddata = get_single_node($node, XMLNS_SRW, 'recordData')
                and $data = get_single_node($recorddata, XMLNS_DC, 'dc')) {

                $record = new stdclass;
                $record->title       = get_node_value($data, XMLNS_DC, 'title');
                $record->description = get_node_value($data, XMLNS_DC, 'description');
                $record->url         = get_node_value($data, XMLNS_DC, 'identifier');
                $record->date        = get_node_value($data, XMLNS_DC, 'date');

                if ($extradata = get_single_node($node, XMLNS_SRW, 'extraRecordData')) {
                    $record->source = get_node_value($extradata, XMLNS_DSM, 'sourceid');
                    $record->format = get_node_value($extradata, XMLNS_DSM, 'metadata', 'name', 'format');
                    if (empty($record->format)) {
                        $record->format = get_node_value($extradata, XMLNS_DSM, 'metadata', 'name', 'DCFormat');
                    }
                    $record->preview = get_node_value($extradata, XMLNS_DSM, 'preview');
                }

                if (!empty($record->url)) {
                    $this->records[] = $record;
                 }
                else {
                    // Skip results which don't lead anywhere
                    debugging("Record $i doesn't have an identifier", DEBUG_ALL);
                }
            }
            else {
                debugging("Cannot parse record data for record $i", DEBUG_NORMAL);
            }

            $i++;
        }
    }

    /**
     * Internal function for parsing the sources included in the server response.
     *
     * @param $sourcenodes DOMNodeList Nodes inside a response's dsm:sources
     */
    function parse_sources($sourcenodes)
    {
        $this->sources = array();

        foreach ($sourcenodes as $node) {
            $record = new stdclass;
            $record->sourceid = $node->getAttribute('id');
            $record->title = get_node_value($node, XMLNS_DSM, 'title');
            $record->description = get_node_value($node, XMLNS_DSM, 'description');
            $record->url = get_node_value($node, XMLNS_DSM, 'link');

            $this->sources[$record->sourceid] = $record;
        }
    }
}

?>