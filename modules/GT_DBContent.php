<?php
/* Collection of utilities to interface with content table */

class GT_DBContent {
    public function __construct() {
    }

    function insertContentSql($rows) {
        // Sql for update database rows to match input $content
        // component_id required
        // assume that all records have the same keys, in the same order
        $fields= array_keys($rows[0]);

        $field_list = implode(',', $fields);
        $sql = "INSERT INTO content ($field_list) VALUES ";

        $values = array();
        foreach ($rows as $row) {
            // quote varchar fields
            if (array_key_exists('content_type', $row))
                $row['content_type'] = "'" . $row['content_type'] . "'";
            if (array_key_exists('content_subtype', $row))
                $row['content_subtype'] = "'" . $row['content_subtype'] . "'";

            $values[] = "(" . implode(',', array_values($row)) . ")";
        }
        $sql .= implode(',',$values);

        $updates = array();
        foreach ($fields as $field) {
            $updates[] = "$field=VALUES($field)";
        }
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);

        return $sql;
    }
}


?>