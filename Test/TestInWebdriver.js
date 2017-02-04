var wd = require('selenium-webdriver');

var SELENIUM_HOST = 'http://localhost:9515/';
var URL = 'http://www.yandex.ru';

var client = new wd.Builder()
    .usingServer(SELENIUM_HOST)
    .withCapabilities({ browserName: 'firefox' })
    .build();

client.get(URL).then(function() {
    client.findElement({ name: 'text' }).sendKeys('test');
    client.findElement({ css: '.b-form-button__input' }).click();

    client.executeScript("return 5;").then(function(data) {
        console.log(data)
    });

});