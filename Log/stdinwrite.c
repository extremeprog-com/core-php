#include<stdio.h>

int main(int argc, char *argv[]) {
    char buff[1024];
    unsigned long n;
    FILE *fp;
    fp = fopen(argv[1], "a");
    while((n = read(0, buff, 1024)) > 0) {
        write(1, buff, n);
        fwrite(buff, 1, n, fp);
    }
    return 0;
}
