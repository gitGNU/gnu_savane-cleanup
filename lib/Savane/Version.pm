#!/usr/bin/perl
# This file is part of the Savane project
# <http://gna.org/projects/savane/>
#
# $Id$
#
# Copyright 2003-2004 (c) Mathieu Roy <yeupou--gnu.org>
# 
# The Savane project is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# The Savane project is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with the Savane project; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
#

use strict "vars";
require Exporter;

# Exports
our @ISA = qw(Exporter);
our @EXPORT = qw(GetVersion );
our $version = 1;

# Return a configuration item
sub GetVersion {
    # Should be a valid release number or
    #     SVN (toward x.x)
    #     x.x (prerelease)
    return "SVN (toward 3.1)";
}

# EOF
