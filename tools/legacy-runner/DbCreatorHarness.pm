# Esegue sub di LIB/db_creator.pm fuori da execute_db_creator. Le sub leggono variabili lessicali del modulo
# ($configHash, $dbDataFolder, $info, …) e scrivono su MySQL: load() carica il loro testo originale nel package
# Harness, che fornisce quelle variabili come globali, e qui si sostituiscono solo le chiamate al DB e al download.
#
#   use DbCreatorHarness;
#   DbCreatorHarness::load($work, qw(createGOTable));
#   Harness::createGOTable(...);   # le tabelle finiscono in $work/tables
package DbCreatorHarness;

use strict;
use warnings;

sub load {
    my ($work, @names) = @_;
    open(my $source, '<', '/legacy/LIB/db_creator.pm') or die "Cannot read db_creator.pm: $!";
    my $code = do { local $/; <$source> };
    my $subs = '';

    for my $name (@names) {
        # Ogni sub finisce dove inizia la documentazione POD della successiva.
        $code =~ /^([ \t]*sub \Q$name\E\s*\{.*?)^=head2/ms or die "sub $name not found in db_creator.pm";
        $subs .= $1;
    }

    $Harness::dbDataFolder = $work;
    $Harness::tempTableFolder = "$work/tables";
    mkdir $Harness::tempTableFolder or die "Cannot create $Harness::tempTableFolder: $!";
    $Harness::configHash = {
        tempTableFolder => $Harness::tempTableFolder,
        headParsNAValues => 'headParsNAValues.log',
        eraseFiles => 'NO',
        uniprotKBDB => 'unused',
    };
    $Harness::database = $Harness::dbDownloaded = 'harness';

    eval "package Harness; no strict 'vars'; our (\$info, \$configHash, \$dbDataFolder, \$tempTableFolder, \$database, \$dbDownloaded); $subs; 1"
        or die $@;
}

# Hash chiave → id interno (da legare con tie) in cui ogni chiave (accession, GO id) esiste e ha per id sé stessa: i mapping
# scritti dal legacy diventano accession → id degli enzimi o dei pathway, dominio → GO id.
package DbCreatorHarness::EveryAccession;

sub TIEHASH { return bless {}, shift }
sub FETCH { return $_[1] }
sub EXISTS { return 1 }

package Harness;

use strict;
use warnings;
use LIB::files_management qw(extract_name delete_file append_str_2_file extract_columns_from_file);
use LIB::annocript_management qw(getUnirefType);

our ($info, $configHash, $dbDataFolder, $tempTableFolder, $database, $dbDownloaded);

sub try_exec_command { return system($_[0]) == 0 ? 1 : 0 }
sub table_2_db {}
sub table_in_db_ok {}
sub download_file { die "The harness does not download: @_\n" }

1;
