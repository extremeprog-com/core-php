var ObjectEditor = {
    setupForm: function (obj, elem, save_callback) {

        var container = [],
            t = this;

        for (var key in obj) {
           container.push(this.addField(key , obj[key]));
        }
        elem = jQuery(elem);
        elem.append('<table class="edit">' + container.join('') + '<tr><td colspan="2"><button class="save">save</button></td></tr></table>');
        elem.find(".save").click(function(){
            var result = ObjectEditor.getForm(elem);
            //console.log(result);
            save_callback(result);
            elem.find(".edited").toggleClass('edited');
        });
        elem.delegate(".item.array input","focus",function(){
            if(jQuery(this).parent().next().length == 0) jQuery(this).closest("td").append(t.renderField('','item array'));
        });
        elem.delegate(".item.array .delete","click",function(e){
            jQuery(this).parent().remove();
        });

        (function(){
            var txt = null;
            elem.delegate(".item input","focus",function(){
                var elem = jQuery(this);
                if(typeof elem.data("old_value") == "undefined") {
                    elem.data("old_value", elem.val());
                };
            });
            elem.delegate(".item input","blur",function(){
                var elem = jQuery(this);
                elem.toggleClass("edited", elem.data("old_value") != elem.val())
            });
        })();
    },
    addField: function (fieldName, value) {
        var field = '',
            cls;
        if (value instanceof Array) {
                for (var i = 0; i < value.length; i++) {
                    field += this.renderField(value[i]);
                }
                field += this.renderField('');
            cls = "array";
        } else {
            field = this.renderField(value);
            cls = "single";
        }
        return '<tr><td>' + fieldName + '</td><td class="item ' + cls + '" data-name="' + fieldName + '">' + field + '</td></tr>';
    },
    renderField: function(value){
        return '<div class="field"><input' + (value ? ' value="' + value.replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g, "&quot;") + '"' : '') + '/><span class="delete">X</span></div>';
    },
    getForm: function(elem) {
        var fields = {};
        jQuery(".item", elem).each(function(){
            var item = jQuery(this);

            if(item.hasClass('array')) {
                (function(){
                    var name = item.data('name'),
                        arr = [];
                    item.find("input").each(function(){
                        arr.push(jQuery(this).val());
                    });
                    while(arr.length && arr[arr.length - 1] == '') {
                        arr.pop();
                    }
                    fields[name] = arr;
                })();
            } else {
               fields[item.data('name')] = item.find("input").val();
            }
        })
        return fields;
    }



}
