var fs = require('fs');

// node geojson_parse.js [get_type] [file]
// node geojson_parse.js [split_feature] [file] [target]
fs.readFile(process.argv[3], function(err, data){
    var json = JSON.parse(data);
    if ('get_type' == process.argv[2]) {
        console.log(json.type);
    } else if ('get_content' == process.argv[2]) {
        console.log((new Buffer(json.content, 'base64')).toString());
    } else if ('split_feature' == process.argv[2]) {
        for (var i = 0; i < json.features.length; i ++) {
            fs.writeFileSync(process.argv[4] + '/' + i + '.json', JSON.stringify(json.features[i]));
        }
    }
});
