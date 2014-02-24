/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-today Andrew Hardie and contributors
 *
 * See http://cwb.sourceforge.net/cqpweb.php
 *
 * This file is part of CQPweb.
 * 
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * CQPweb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function refresh_captcha()
{
	/* get a new captcha ref from the server */
	var req = new XMLHttpRequest();
	
	req.open("GET", "../usr/redirect.php?redirect=ajaxNewCaptchaImage&uT=y", true);

	/* here we use only a very basic anonymous function
	 * that runs another function on the XML doc received back. */
	req.onreadystatechange = 
		function()
		{
			if (req.readyState != 4) 
				return;
			if (req.status != 200 && req.status != 304)
				return;
			if (req.responseText == null)
			{
				alert("Could not load new CAPTCHA image!");
			}
			else
			{
				which = req.responseText;

				/* stick the captcha ref into the form */
				document.getElementById('captchaRef').value = which;

				/* change the image */
				document.getElementById('captchaImg').src =
					"../usr/redirect.php?redirect=captchaImage&which=" +
					which + 
					"&uT=y&cacheblock=" + 
					Math.floor(Math.random()*99999999);
			}
		}	
	req.send();


}

function receive_refresh_captcha()
{

}