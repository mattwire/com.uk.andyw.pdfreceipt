<?php

/* 
 * PDF Receipt Extension for CiviCRM - Circle Interactive 2012
 * Author: andyw@circle
 *
 * Distributed under the GNU Affero General Public License, version 3
 * http://www.gnu.org/licenses/agpl-3.0.html 
 */

class CRM_Contribute_Receipt_Default extends CRM_Contribute_Receipt {
    
    public function __construct() {
        $this->format = array(
            'name'         => $this->name(), 
            'paper-size'   => 'A4',
            'metric'       => 'mm', 
            'margin-top'   => 15, 
            'margin-left'  => 15,
            'margin-right' => 15,
            'font-size'    => 10
        );
        parent::__construct();
        
        // use setDebug() to display container outlines
        //$this->setDebug();
    
    }
    
    public function name() {
        return ts('Default A4');
    }
    
}