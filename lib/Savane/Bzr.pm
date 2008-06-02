#!/usr/bin/perl
# Copyright (C) 2008  Sylvain Beucler
# 
# This file is part of Savane.
# 
# Savane is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
# 
# Savane is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Create bzr repository.

use strict;
use warnings;

require Exporter;
our @ISA = qw(Exporter);
our @EXPORT = qw(BzrMakeArea);
our $version = 1;

sub BzrMakeArea {
    my ($name,$dir_bzr) = @_;
    my $warning = '';

    # %PROJECT is not mandatory, but if it is missing, it may well be 
    # a major misconfiguration.
    # It should only happen if a directory has been set for a specific 
    # project.
    unless ($dir_bzr =~ s/\%PROJECT/$name/) {
	$warning = " (The string \%PROJECT was not found, there may be a group type serious misconfiguration)";
    }

    unless (-e $dir_bzr) {
	# Layout: /srv/bzr/sources/project_name
        #         /srv/bzr/sources/project_name/other_module
	
	# Create a repository
	my $old_umask = umask(0002);

	system('chmod', 'g+s', $dir_bzr);
	system('chgrp', '-R', $name, $dir_bzr);

	# Create folder for subrepositories (need to code multi-repo support first)
	# TODO: precise directory location
	#system('mkdir', '-m', '2775', ".../$name/");
	#system('chown', "root:$name", ".../$name/");

	# Clean-up environment
	umask($old_umask);

	return ' '.$dir_bzr.$warning;	
    }
    return 0;
}
