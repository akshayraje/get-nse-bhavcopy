Get NSE Bhavcopy
================

This is a simple PHP script that can be used to get [end of day bhavcopy CSV file from nseindia.com](https://www.nseindia.com/products/content/equities/equities/archieve_eq.htm) and dump it in MySQL. You can use this to download the bhavcopy of a specific date in past or the current day. Handy for using as a crontask to fetch the data daily.

The script is designed to provide detailed feedback when used via browser as well as via commandline.

## Usage

### Via browser
Current date: http://example.com/path/to/file/get-nse-bhavcopy.php

Specific date: http://example.com/path/to/file/get-nse-bhavcopy.php?date=06-Sep-2016

### Via commandline
Current date: php path/to/file/get-nse-bhavcopy.php

Specific date: php path/to/file/get-nse-bhavcopy.php date=06-Sep-2016

## Installation

1. Set-up your database and added the required table using create-table.sql
2. Edit the first few lines of get-nse-bhavcopy.php to add your database details (check instructions in comments)
