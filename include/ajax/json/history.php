<?php
// JSON response, get history entries

GLOBAL $dbh;
###
# Handle different histories (basic or detail)
if ( !empty($_GET["id"]) ){
    # Get History of a Item
    $basic_query = ' fk_id_item='.$_GET["id"].'
                AND action <> "edited"';

    $show_item_links = FALSE;

}else{
    $show_item_links = TRUE;
    # Show all entries but still with sql filters (basic history entries, no details about services)
    $basic_query = 
              ' (action="created"'
            . ' OR action="removed"'
            . ' OR action="edited"'
            . ' OR action="general" )'
            . ' OR action ="module"'
            . ' OR (action ="modified"'
                . ' AND (attr_name = "Class" OR attr_name = "Attribute"))';

}



/* Paging */
$sLimit = "";
if ( isset( $_GET['iDisplayStart'] ) AND ( !empty($_GET['iDisplayLength']) AND $_GET['iDisplayLength'] != "-1" ) )
{
    $sLimit = "LIMIT ".mysqli_real_escape_string( $dbh, $_GET['iDisplayStart'] ).", ".
        mysqli_real_escape_string( $dbh, $_GET['iDisplayLength'] );
}

/* Ordering */
if ( isset( $_GET['iSortCol_0'] ) )
{
    $sOrder = "ORDER BY  ";
    for ( $i=0 ; $i<mysqli_real_escape_string( $dbh, $_GET['iSortingCols'] ) ; $i++ )
    {
        $sOrder .= fnColumnToField(mysqli_real_escape_string( $dbh, $_GET['iSortCol_'.$i] ))."
            ".mysqli_real_escape_string( $dbh, $_GET['sSortDir_'.$i] ) .", ";
    }
    $sOrder = substr_replace( $sOrder, "", -2 );
}

/* Filtering */
$sWhere = "";
if ( $_GET['sSearch'] != "" )
{
    # split each word on space
    $search_words = explode(" ", $_GET['sSearch']);
    $sWhere = "AND ( ";
    $i = 0;
    foreach ($search_words AS $search_word) {
        # after the first search, add a AND
        if ($i > 0) $sWhere .= ' AND ';
        # concatenate all history fields with separator and search word
        $sWhere .= "CONCAT_WS(',', LOWER(user_str), action, attr_name, LOWER(attr_value), timestamp ) LIKE '%".mysqli_real_escape_string( $dbh, strtolower($search_word) )."%'";
        $i++;
    }
    $sWhere .= " ) ";
    
}

$sQuery = 'SELECT SQL_CALC_FOUND_ROWS *
    FROM History
    WHERE ('
        . $basic_query
    . ')';

    $sQuery .= "
        $sWhere
        $sOrder
        $sLimit
    ";


# for debugging query, but JSON load will then fail
if ($debug == "yes") echo $sQuery;


$rResult = mysqli_query( $dbh, $sQuery ) or die('Query: '.$sQuery.'<br><br>'.mysqli_error($dbh));

$sQuery = "
    SELECT FOUND_ROWS()
";
$rResultFilterTotal = mysqli_query( $dbh, $sQuery ) or die(mysqli_error($dbh));
$aResultFilterTotal = mysqli_fetch_array($rResultFilterTotal);
$iFilteredTotal = $aResultFilterTotal[0];

$sQuery = "
    SELECT COUNT(id_hist)
    FROM   History
    WHERE "
    . $basic_query;

$rResultTotal = mysqli_query( $dbh, $sQuery ) or die(mysqli_error($dbh));
$aResultTotal = mysqli_fetch_array($rResultTotal);
$iTotal = $aResultTotal[0];


if (function_exists("json_encoded")){
    // If json_ecode we use the php function to encode the array
    // as example code from DataTables
    
    $aColumns = array( 'timestamp', 'user_str', 'action', 'attr_name', 'attr_value', 'id_hist' );
    
    $output = array(
        "sEcho" => intval($_GET['sEcho']),
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => array()
    );
     
    while ( $aRow = mysqli_fetch_array( $rResult ) )
    {
        $row = array();
        for ( $i=0 ; $i<count($aColumns) ; $i++ )
        {
            if ( $aColumns[$i] == "version" )
            {
                $row[] = ($aRow[ $aColumns[$i] ]=="0") ? '-' : $aRow[ $aColumns[$i] ];
            }
            else if ( $aColumns[$i] != ' ' )
            {
                $row[] = $aRow[ $aColumns[$i] ];
            }
        }
        # do some stuff bevore generating output

        # if object name contains password, and PASSWD_DISPLAY is 0 (made in called function), do not show password
        if ( stristr($aRow["attr_name"], "password") ){
            $aRow["attr_value"] = show_password($aRow["attr_value"]);
        }
        # item linking
        if ( !empty($aRow["fk_id_item"]) AND $show_item_links ){
            if ($aRow["action"] == "removed" AND $aRow["attr_name"] == "service"){
                # removed services will link to hosts service list view
                $aRow['attr_value'] = '<a href="modify_item_service.php?id='.$aRow["fk_id_item"].'">'.$aRow["attr_value"].'</a>';
            }else{
                # all other entries will link to its detail view
                $aRow['attr_value'] = '<a href="detail.php?id='.$aRow["fk_id_item"].'">'.$aRow["attr_value"].'</a>';
            }
        }else{
            # entries which are removed, do not have any link
        }
        $output['aaData'][] = $row;
    }
     
    echo json_encode( $output );
    
}else{
    // else we have a manual process to create JSON data
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
    $sOutput .= '"aaData": [ ';
    
    
    while ( $aRow = mysqli_fetch_array( $rResult ) )
    {
        # do some stuff bevore generating output
    
        # if object name contains password, and PASSWD_DISPLAY is 0 (made in called function), do not show password
        if ( stristr($aRow["attr_name"], "password") ){
            $aRow["attr_value"] = show_password($aRow["attr_value"]);
        }
        # item linking
        if ( !empty($aRow["fk_id_item"]) AND $show_item_links ){
            if ($aRow["action"] == "removed" AND $aRow["attr_name"] == "service"){
                # removed services will link to hosts service list view
                $aRow['attr_value'] = '<a href="modify_item_service.php?id='.$aRow["fk_id_item"].'">'.$aRow["attr_value"].'</a>';
            }else{
                # all other entries will link to its detail view
                $aRow['attr_value'] = '<a href="detail.php?id='.$aRow["fk_id_item"].'">'.$aRow["attr_value"].'</a>';
            }
        }else{
            # entries which are removed, do not have any link
        }
    
        # create output (JSON)
        $sOutput .= "[";
        $sOutput .= '"'.addslashes($aRow['timestamp']).'",';
        $sOutput .= '"'.addslashes($aRow['user_str']).'",';
        $sOutput .= '"'.addslashes($aRow['action']).'",';
        $sOutput .= '"'.addslashes($aRow['attr_name']).'",';
        $sOutput .= '"'.addslashes($aRow['attr_value']).'",';
        $sOutput .= '"'.addslashes($aRow['id_hist']).'"';
        $sOutput .= "],";
    }
    
    $sOutput = substr_replace( $sOutput, "", -1 );
    $sOutput .= '] }';
    // This should fix a json problem
    $sOutput = str_replace("\'", "'", $sOutput);
    
    echo $sOutput;
}


function fnColumnToField( $i )
{
    if ( $i == 0 )
        return "timestamp";
    else if ( $i == 1 )
        return "user_str";
    else if ( $i == 2 )
        return "action";
    else if ( $i == 3 )
        return "attr_name";
    else if ( $i == 4 )
        return "attr_value";
    else if ( $i == 5 )
        return "id_hist";
}


?>
