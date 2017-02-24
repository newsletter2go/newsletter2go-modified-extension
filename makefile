version = 0_0_00
outfile = Modified_nl2go_$(version).zip

$(outfile):
	zip -r  build.zip ./src/*
	mv build.zip $(outfile)

clean:
	rm -rf $(outfile)
