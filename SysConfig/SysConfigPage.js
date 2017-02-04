var SysConfigPage = Class.create(AjaxPage, ({
     run: function(){
         Servicer.call( 'config.loadConfig', {} , function(data){
             if(data.responseJSON) data = data.responseJSON;
             var config = data.RESULT.SysConfig;
             
             if(config=='') config = {};

             Servicer.call('config.loadSchema',{}, function(data){
                if(data.responseJSON) data = data.responseJSON;
                var schema = data.RESULT.SysConfigSchema;

                if(schema=='') schema = {};

                for(var section in schema) {

                    if(!config[section]) 
                        config[section] = {};

                    for(var key in schema[section])
                        if(!config[section][key]){
                            switch(schema[section][key]){
                                case 'array':   config[section][key] = [];break;
                                default :       config[section][key] = '';break;
                            }
                        }
                }
                 
                for(var section in config)
                 
                    if(!schema[section])
                        delete config[section];
                    else
                        for(var param in config[section])
                            if(!schema[section][param])
                                delete config[section][param];
                 
                for(section in config) (function(section){
                    
                    jQuery('<h2>section: '+ section +'</h2><div id="section-'+section+'">').appendTo('#config');
                    ObjectEditor.setupForm(config[section], "#section-"+section, function(data ) {
                        Servicer.call( 'config.saveConfig', { section:section, data: data } );
                    });

                })(section);

             });
         });

   }
}));
