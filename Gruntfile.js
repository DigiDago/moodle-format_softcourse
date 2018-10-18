const sass = require('node-sass');


module.exports = function(grunt) {

    grunt.initConfig({
        sass: {
            options: {
                implementation: sass,
                sourceMap: true
            },
            dist: {
                files: {
                    'styles.css': 'scss/styles.scss'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-sass');

    grunt.registerTask('default', ['sass']);

};