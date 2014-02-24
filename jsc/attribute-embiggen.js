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

// functions for adding extra lines to the "install corpus" forms
function add_s_attribute_row()
{
	var number = document.getElementById('s_instruction_cell').rowSpan + 1;
	document.getElementById('s_instruction_cell').rowSpan = number.toString();

	var theTr = document.createElement('tr');
	var theTd = document.createElement('td');
	var theIn = document.createElement('input');
	
	theTd.setAttribute('colspan','6');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('name','customS'+number);
	theIn.setAttribute('onKeyUp','check_c_word(this)');
	
	theTr.appendChild(theTd);
	theTd.appendChild(theIn);
	
	document.getElementById('s_att_row_1').parentNode.appendChild(theTr);
//	document.getElementById('s_att_row_1').parentNode.insertBefore(theTr, document.getElementById('p_att_header_row'));
}


function add_p_attribute_row()
{
	var number = 1 + Number(document.getElementById('pNumRows').value);
	document.getElementById('pNumRows').value = number;
	
	var namebase = document.getElementById('inputNameBase').value;

	var theTr = document.createElement('tr');

	var theTd = document.createElement('td');
	var theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','radio');
	theIn.setAttribute('name', namebase+'PPrimary');
	theIn.value = number;
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','15');
	theIn.setAttribute('name', namebase+'PHandle'+number);
	theIn.setAttribute('onKeyUp','check_c_word(this)');
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'PDesc'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'PTagset'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'Purl'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','checkbox');
	theIn.setAttribute('value','1');
	theIn.setAttribute('name',namebase+'Pfs'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	document.getElementById('p_att_row_1').parentNode.insertBefore(theTr, document.getElementById('p_embiggen_button_row'));
}

