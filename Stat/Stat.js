var Stat = classes.Stat = {

    writeStat: function(section, parameters, action) {
        Servicer.call('stat.writeStat', {
            section: section,
            parameters: parameters,
            action: action
        });
    }
    
}