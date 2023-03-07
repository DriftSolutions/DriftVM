
IF (NOT EXISTS ${DSL_DIRECTORY})
	execute_process(COMMAND git clone https://github.com/DriftSolutions/DSL.git "${DSL_DIRECTORY}")
ENDIF()
IF (NOT EXISTS ${DSL_DIRECTORY})
        message(FATAL_ERROR "Could not clone the DSL repository!")
ENDIF()

AUX_SOURCE_DIRECTORY(${DSL_DIRECTORY}/src SRCFILES)
AUX_SOURCE_DIRECTORY(${DSL_DIRECTORY}/src/algo ALGOFILES)
AUX_SOURCE_DIRECTORY(${DSL_DIRECTORY}/src/mysql MYSQLFILES)

message(STATUS "DSL Mode: Static")
add_library (dsl STATIC ${SRCFILES} ${ALGOFILES} ${MYSQLFILES})
