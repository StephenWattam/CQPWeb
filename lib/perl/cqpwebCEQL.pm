# -*-cperl-*-
## CQPweb extension of the CEQL grammar

use warnings;
use strict;

package cqpwebCEQL;
use base 'CWB::CEQL';

use Encode;
use HTML::Entities;

=head1 NAME

cqpwebCEQL - CQPweb extension of the Common Elementary Query Language (CEQL)

=head1 SYNOPSIS

  use cqpwebCEQL;
  our $CEQL = new bncCEQL;

  $CEQL->SetParam("default_ignore_case", 0); # case-sensitive query mode

  # $ceql_query must be in utf-8
  $cqp_query = $CEQL->Parse($ceql_query);    # returns CQP query in canonical BNCweb encoding

  if (not defined $cqp_query) {
    $html_msg = $ceql->HtmlErrorMessage;     # ready-made HTML error message
    print "<html><body>$html_msg</body></html>\n";
    exit 0;
  }

=cut

# constructor: the CQPweb version of this sets up UNDEFINED parameters.
# Everything therefore MUST be set by the calling function. Or it won't work.
# Exception: ignore_case is set off, and ignore_diac is set off.
# Exception: <s> is allowed. If anything else is allowed, reset this parameter.
sub new {
  my $class = shift;
  my $self = new CWB::CEQL;


  $self->NewParam("combo_attribute", undef);


  $self->SetParam("pos_attribute", undef);
  $self->SetParam("lemma_attribute", undef);
  $self->SetParam("simple_pos", undef);
  $self->SetParam("simple_pos_attribute", undef);
  $self->SetParam("s_attributes", { "s" => 1 });
  $self->SetParam("default_ignore_case", 0);
  $self->SetParam("default_ignore_diac", 0);

  return bless($self, $class);
}



# override lemma_pattern rule to provide support for {book/V} notation
sub lemma_pattern {
  my ($self, $lemma) = @_;
  
  # split lemma into headword pattern and optional simple POS constraint
  my ($hw, $tag, $extra) = split /(?<!\\)\//, $lemma;
  die "Only a single ''/'' separator is allowed between the first and second search terms in a {.../...} search.\n"
    if defined $extra;
  die "Missing first search term (nothing before the / in {.../...} ); did you mean ''_{$tag}''?\n"
    if $hw eq "";
    
  # translate wildcard pattern for headword and look up simple POS if specified
  my $regexp = $self->Call("wildcard_pattern", $hw);
  
  if (defined $tag) {
    # simple POS specified => look up in $simple_pos and combine with $regexp
    
    # before looking up the simple POS, we must check that the mapping table is defined
    my $simple_pos = $self->GetParam("simple_pos");
    die "Searches of the form _{...}  and {.../...} are not available.\n"
      unless ref($simple_pos) eq "HASH";
    
    my $tag_regexp = $simple_pos->{$tag};
    if (not defined $tag_regexp) {
      my @valid_tags = sort keys %$simple_pos;
      die "'' $tag '' is not a valid tag in this position (available tags: '' @valid_tags '')\n";
    }
    
    my $attr = $self->GetParam("combo_attribute");
    if (defined $attr) {
      $regexp =~ s/^"//; $regexp =~ s/"$//; # remove double quotes around regexp so it can be combined with POS constraint
      return "$attr=\"($regexp)_${tag_regexp}\"";
    }
    else {
      my $first_attr = $self->GetParam("lemma_attribute")
        or die "Searches of the form {.../...} are not available.\n";
      my $second_attr = $self->GetParam("simple_pos_attribute")
        or die "Searches of the form {.../...} are not available.\n";
      return "($first_attr=$regexp & $second_attr=\"${tag_regexp}\")";
    }
  }
  else {
    # no simple POS specified => match the normal lemma attribute.
    my $attr = $self->GetParam("lemma_attribute")
      or die "Searches of the form {...} are not available.\n";
    return "$attr=$regexp";
  }
}

=head1 COPYRIGHT

Copyright (C) 1999-2008 Stefan Evert [http::/purl.org/stefan.evert]
(modified very slightly by Andrew Hardie for CQPweb)

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut

1;