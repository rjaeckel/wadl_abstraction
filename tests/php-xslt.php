<?php
/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 14.03.16
 * Time: 23:02
 */

$root='../';
$thirdParty=$root.'3rdparty/';
$wadl_schemata='wadl_schemata';
$target='php-xslt.out/';
$locations=['ipcsystems.wadl-stylesheet','mnot.wadl_stylesheets'];

$filesMatch = function($thirdParty,$suf,string ...$arr):array {
	$match="ls -1 ".implode(array_map(function($ns)use($thirdParty,$suf){
			return $thirdParty.$ns."/*.$suf";
		},$arr),' ');
	echo "SEARCH: $match\n";
	return explode("\n",trim(`$match`));
};
#$wadl_files=$filesMatch($thirdParty, 'wadl', ...$locations);
$wadl_files=$filesMatch($root,'wadl',$wadl_schemata);
echo "RESULT: ".implode(' ',$wadl_files)."\n";
$xsl_files=$filesMatch( $thirdParty,'xsl', ...$locations);
echo "RESULT: ".implode(' ',$xsl_files)."\n";



foreach ($xsl_files as $xsl) {
	foreach ($wadl_files as $wadl) {
		#$wadl=trim($wadl);$xsl=trim($xsl);
		echo "\nSTART $xsl with $wadl\n";
		$xslt = new xsltProcessor;
		echo "STYLESHEET $xsl\n";
		$xslt->importStylesheet(@DomDocument::load($xsl));
		echo "TRANSFORM $wadl\n";
		file_put_contents(
			$target.basename($xsl).'_'.basename($wadl).'.html',
			$xslt->transformToXml(@DomDocument::load($wadl))
		);
		echo "END $xsl with $wadl\n";
	}
}

