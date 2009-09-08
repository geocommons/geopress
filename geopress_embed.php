<?php

class GeoPressEmbed{

    function geocommons_map($maker_id, $width=300, $height=300, $host= "geocommons.com") {
        $output = "<style>#maker_map_$maker_id {width: ".$width."px; height: ".$height."px;}</style><script type='text/javascript' charset='utf-8' src='http://maker.$host/javascripts/embed.js'></script>
<script type='text/javascript' charset='utf-8'>";
        $output .= "Maker.maker_host='http://maker.$host';Maker.finder_host='http://finder.$host';Maker.core_host='http://core.$host';";
        $output .= "Maker.load_map('maker_map_$maker_id', '$maker_id');</script><div id='maker_map_$maker_id'></div>";
        return $output;
    }
}

?>