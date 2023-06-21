<?php

/**
 *  ------------------------------------------------------------------------
 *  Chris Gralike Ticket Filter
 *  Copyright (C) 2023 by Chris Gralike
 *  ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Ticket Filter project.
 *
 * Ticket Filter plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Ticket Filter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ticket filter. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 *  @package  	    TicketFilter
 *  @version	    1.0.0
 *  @author         Chris Gralike
 *  @copyright 	    Copyright (c) 2023 by Chris Gralike
 *  @license    	MIT
 *  @see       	    https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		    https://github.com/DonutsNL/ticketfilter
 *  @since     	    1.0.0
 *  @todo           !Keep it stupid simple!
 *                  -Create a fancy configuration page
 *                   -Config [Followup Match string] [AutoSolve Match String] [Supress followups] [regEx101 testlink/comment]
 *                   -Option to automatically merge duplicate oldest/latest ticket if more then 1 ticket is found with matchstring.
 *                   -Option to automatically link with closed ticket(s) on match closed (how to deal with multiple closed tickets?)
 *                  -Default GLPI behaviour is to load Plugin trazilion times with each dashboard graph, maybe create singleton pattern/caching?
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;

use CommonDropdown;
use Html;

class Config extends CommonDropdown {

    public function showForm($ID, array $options = []) {
       global $CFG_GLPI;
 
       $this->initForm($ID, $options);
       $this->showFormHeader($options);
 
       if (!isset($options['display'])) {
          //display per default
          $options['display'] = true;
       }
 
       $params = $options;
       //do not display called elements per default; they'll be displayed or returned here
       $params['display'] = false;
 
       $out = '<tr>';
       $out .= '<th>' . __('My label', 'Ticket Filter') . '</th>';
 
       $objectName = autoName(
          $this->fields["name"],
          "name",
          (isset($options['withtemplate']) && $options['withtemplate']==2),
          $this->getType(),
          $this->fields["entities_id"]
       );
 
       $out .= '<td>';
       $out .= Html::autocompletionTextField(
          $this,
          'name',
          [
             'value'     => $objectName,
             'display'   => false
          ]
       );
       $out .= '</td>';
 
       $out .= $this->showFormButtons($params);
 
       if ($options['display'] == true) {
          echo $out;
       } else {
          return $out;
       }
    }
 }