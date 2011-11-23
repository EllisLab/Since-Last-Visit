<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2007 - 2011 EllisLab, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
ELLISLAB, INC. BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of EllisLab, Inc. shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from EllisLab, Inc.
*/

$plugin_info = array(
	'pi_name'			=> 'Since Last Visit',
	'pi_version'		=> '2.0.1',
	'pi_author'			=> 'Donnie Adams',
	'pi_author_url'		=> '',
	'pi_description'	=> 'Attempts to point out various actions that have happened since a visitor\'s last visit to your site.',
	'pi_usage'			=> Since_last_visit::usage()
);

/**
 * Since_last_visit Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			ExpressionEngine Dev Team
 * @copyright		Copyright (c) 2004 - 2011, EllisLab, Inc.
 * @link			http://expressionengine.com/downloads/details/since_last_visit/
 */

class Since_last_visit
{
	var $cookie_name		= 'pi_lastvisit';
	var $cookie_expire		= 24; // default time before new cookie/timestamp can be set reset in hours.
	var $last_visit_date	= '';
	
	/**
	 * Constructor
	 *
	 */

	function Since_last_visit()
	{
		$this->EE =& get_instance();
	}
	
	// --------------------------------------------------------------------
	
	/**
	* Stats
	*
	* @access	public
	* @return	string
	*/
	function stats()
	{
		/*---------------------------------------
		  Get last visit date
		-----------------------------------------*/

		if ($this->_eval_cookie() == 'SET')
		{
			$visit_ts = $this->last_visit_date;
		}
		else
		{
			$visit_ts = $this->EE->localize->now;
		}

		$stag = array(
						'new_entry_total'	=> 0,
						'new_comment_total' => 0,
						'last_visit_date'	=> $visit_ts
						);

		if ($visit_ts != $this->EE->localize->now)
		{
			/*---------------------------------------
			  Get total entries since last visit
			-----------------------------------------*/

			$this->EE->db->select('COUNT(exp_channel_titles.entry_id) AS new_entry_total', FALSE);
			$this->EE->db->where('channel_titles.status', 'open');
			$this->EE->db->where('(exp_channel_titles.entry_date >= '.$visit_ts.')', NULL, FALSE);
			$this->EE->db->where('(exp_channel_titles.expiration_date = 0 || exp_channel_titles.expiration_date > '.$this->EE->localize->now.')', NULL, FALSE);
			
			$query = $this->EE->db->get('channel_titles');
			$stag['new_entry_total'] = $query->row('new_entry_total');


			/*---------------------------------------
			  Get total comments since last visit
			-----------------------------------------*/

			$this->EE->db->select('COUNT(exp_comments.comment_id) AS new_comment_total', FALSE);
			$this->EE->db->where('comments.status', 'o');
			$this->EE->db->where('exp_comments.comment_date >= ', $visit_ts, FALSE);

			$query = $this->EE->db->get('comments');
			$stag['new_comment_total'] = $query->row('new_comment_total');

		}

		/*---------------------------------------
		  Parse Template
		-----------------------------------------*/

		foreach ($this->EE->TMPL->var_single as $key => $val)
		{
			if ($key == 'new_entry_total')
			{
				$this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($key, $stag['new_entry_total'], $this->EE->TMPL->tagdata);
			}

			if ($key == 'new_comment_total')
			{
				$this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($key, $stag['new_comment_total'], $this->EE->TMPL->tagdata);
			}

			if (preg_match('/last_visit_date/', $key))
			{
				$this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($key, $this->EE->localize->decode_date($val, $stag['last_visit_date']), $this->EE->TMPL->tagdata);
			}
		}

		return $this->EE->TMPL->tagdata;
	}

	// --------------------------------------------------------------------
	
	/**
	* Evaluate
	*
	* @access	public
	* @return	string
	*/
	function evaluate()
	{
		static $visit_ts;

		/*---------------------------------------
		  Get last visit date timestamp
		-----------------------------------------*/

		if ( ! isset($visit_ts))
		{
			if ($this->_eval_cookie() == 'SET')
			{
				$visit_ts = $this->EE->localize->set_localized_time($this->last_visit_date);
			}
			else
			{
				$visit_ts = '';
			}
		}

		/*---------------------------------------
		 Fetch entry date UNIX timestamp
		-----------------------------------------*/

		$timestamp = $this->EE->TMPL->fetch_param('timestamp');

		/*----------------------------------------
		 Parse conditionals variables
		----------------------------------------*/
		
		$new_since_last_visit = (is_numeric($visit_ts) &&
								is_numeric($timestamp) &&
								$visit_ts < $timestamp);
		
		$variables = array(
			array(
				'since_last_visit'		=> $new_since_last_visit,
				'not_since_last_visit'	=> ! $new_since_last_visit
			)
		);
		
		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
	}

	// --------------------------------------------------------------------
	
	/**
	* Function Name
	*
	* Function description
	*
	* @access	private
	* @param	string
	* @return	string
	*/
	function _eval_cookie($expire = '')
	{
		if ( ! ($visit_ts = $this->EE->input->cookie($this->cookie_name)))
		{
			return 'NOT_SET';
		}
		elseif (is_numeric($visit_ts))
		{
			if (is_numeric($expire) && (($visit_ts + ($expire * 3600)) < $this->EE->localize->now))
			{
				return 'EXPIRED';
			}

			$this->last_visit_date = $visit_ts;

			return 'SET';
		}
		else
		{
			return 'EXPIRED';
		}
	}

	// --------------------------------------------------------------------
	
	/**
	* Set cookie
	*
	* @access	public
	* @return	void
	*/
	function set_cookie()
	{
		$expire = ( ! is_numeric($this->EE->TMPL->fetch_param('expire'))) ? $this->cookie_expire : $this->EE->TMPL->fetch_param('expire');

		switch ($this->_eval_cookie($expire))
		{
			case 'NOT_SET':
				$this->EE->functions->set_cookie($this->cookie_name, $this->EE->localize->now, $expire * 3600);
				//return 'NOT_SET';
				break;

			case 'EXPIRED':
				$this->EE->functions->set_cookie($this->cookie_name, '');
				$this->EE->functions->set_cookie($this->cookie_name, $this->EE->localize->now, $expire * 3600);
				//return 'EXPIRED';
				break;

			default:
				//return 'SET';
				break;
		}
		return;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Usage
	 *
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string
	 */
	function usage()
	{
		ob_start(); 
		?>

		Since Last Visit is an ExpressionEngine plugin that attempts to point out various actions that have happened since a visitor's last visit to your site.


		=======================================
		=========== SET COOKIE TAG ============
		=======================================

		The "set cookie" template tag saves a timestamp of the visitor's last visit to your site in a cookie on the visitor's computer.
		The tag can and should appear only once in any "Web Page" type template, i.e. index, archives, categories, and comments templates.

		==============
		USAGE EXAMPLE
		==============

		{exp:since_last_visit:set_cookie expire="24"}

		==============
		TAG PARAMETERS
		==============

		expire=
			The amount of time in hours before the visitor's timestamp is reset.
			The default time is 24 hours.

			Example: {exp:since_last_visit:set_cookie expire="24"}
		-------------------

		=======================================
		============= STATS TAG ===============
		=======================================

		==============
		USAGE EXAMPLE
		==============

		{exp:since_last_visit:stats}

		{new_entry_total} new post/s since your last visit<br />
		{new_comment_total} new comment/s since your last visit<br />
		Last visited on {last_visit_date format="%m/%d/%y"} at {last_visit_date format="%h:%i %a"}

		{/exp:since_last_visit:stats}

		=============
		TAG VARIABLES
		=============

		{new_entry_total}
			Total number of new entries that were posted.
		-------------------

		{new_comment_total}
			Total number of new comments that were made.
		-------------------

		{last_visit_date format=""}
			The date and time of the visitor's last visit.
		-------------------

		=======================================
		============= EVALUATE TAG ============
		=======================================

		The "Evaluate" template tag	 allows you to distinguish between new and old content relative to your visitor's last visit date.

		==============
		USAGE EXAMPLE
		==============

		<h2 class="sidetitle">Most recent entries</h2>
		<ul>
		{exp:channel:entries channel="channel1" orderby="date" sort="desc" limit="15" dynamic="off"}

		{exp:since_last_visit:evaluate timestamp="{entry_date format="%U"}"}
		{if since_last_visit}
		<li>
			<a href="{title_permalink=channel/index}" title="New since your last visit.">{title}</a>
			<img src="http://localhost/ee/new.gif" alt="NEW !" />
		</li>
		{/if}

		{if not_since_last_visit}
		<li>
			<a href="{title_permalink=channel/index}">{title}</a>
		</li>
		{/if}
		{/exp:since_last_visit:evaluate}

		{/exp:channel:entries}
		</ul>

		==============
		TAG PARAMETERS
		==============

		timestamp=
			The content's creation date in UNIX timestamp format.
		-------------------

		=====================
		CONDITIONAL VARIABLES
		=====================

		{if since_last_visit}
		{/if}
			Allows you to conditionally display content if the content was created after the visitor's last visit.
		-------------------

		{if not_since_last_visit}
		{/if}
			Allows you to conditionally display content if the content was created before the visitor's last visit.
		-------------------

		Version 2.0
		******************
		- Updated plugin to be 2.0 compatible


		<?php
		$buffer = ob_get_contents();

		ob_end_clean(); 

		return $buffer;
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file pi.since_last_visit.php */
/* Location: ./system/expressionengine/third_party/since_last_visit/pi.since_last_visit.php */